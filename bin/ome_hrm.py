#!/usr/bin/env python

"""OMERO connector for the Huygens Remote Manager (HRM).

This wrapper processes all requests from the HRM web interface to communicate
to an OMERO server for listing available images, transferring data, etc.
"""

# pylint: disable=superfluous-parens

# TODO:
# - trees for different groups
# - proper logging, separate logfile for the connector
# - redirect logging of CLI
# - offer download of OME-TIFFs?


import sys
import hrm_config

# optionally put EXT_LIB into our PYTHONPATH:
if 'PYTHON_EXTLIB' in hrm_config.CONFIG:
    sys.path.insert(0, hrm_config.CONFIG['PYTHON_EXTLIB'])

try:
    import argparse
    import os
    import json
    import re
except ImportError:
    print "ERROR importing required Python packages!"
    print "Current PYTHONPATH: ", sys.path
    sys.exit(1)

# try to put OMERO into our PYTHONPATH:
if 'OMERO_PKG' in hrm_config.CONFIG:
    OMERO_LIB = '%s/lib/python' % hrm_config.CONFIG['OMERO_PKG']
    sys.path.insert(0, OMERO_LIB)
else:
    print "Could not find configuration value 'OMERO_PKG', omitting."
try:
    from omero.gateway import BlitzGateway
except ImportError:
    print "ERROR importing Python bindings for OMERO!"
    print "Current PYTHONPATH: ", sys.path
    sys.exit(2)

# the connection values
HOST = hrm_config.CONFIG['OMERO_HOSTNAME']
if 'OMERO_PORT' in hrm_config.CONFIG:
    PORT = hrm_config.CONFIG['OMERO_PORT']
else:
    PORT = 4064


def omero_login(user, passwd, host, port):
    """Establish the connection to an OMERO server.

    Parameters
    ==========
    user : str - OMERO user name (e.g. "demo_user_01")
    passwd : str - OMERO user password
    host : str - OMERO server hostname to connect to
    port : int - OMERO server port number (e.g. 4064)

    Returns
    =======
    conn : omero.gateway._BlitzGateway - OMERO connection object
    """
    conn = BlitzGateway(user, passwd, host=host, port=port, secure=True,
                        useragent="HRM-OMERO.connector")
    conn.connect()
    return conn


def tree_to_json(obj_tree):
    """Create a JSON object with a given format from a tree."""
    return json.dumps(obj_tree, sort_keys=True,
                      indent=4, separators=(',', ': '))


def print_children_json(conn, id_str):
    """Print the child nodes of the given ID in JSON format.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    id_str : str - OMERO object ID string (e.g. "G:23:Image:42")

    Returns
    =======
    bool - True in case printing the nodes was successful, False otherwise.
    """
    try:
        children = gen_children(conn, id_str)
    except:
        print "ERROR generating OMERO tree / node!"
        return False
    print tree_to_json(children)
    return True


def gen_obj_dict(obj, id_pfx=''):
    """Create a dict from an OMERO object.

    Returns
    =======
    obj_dict : dict - dictionary with the following structure:
    {
        'children': [],
        'id': 'Project:1154',
        'label': 'HRM_TESTDATA',
        'owner': u'demo01',
        'class': 'Project'
    }
    """
    obj_dict = dict()
    obj_dict['label'] = obj.getName()
    obj_dict['class'] = obj.OMERO_CLASS
    if obj.OMERO_CLASS == 'Experimenter':
        obj_dict['owner'] = obj.getId()
        obj_dict['label'] = obj.getFullName()
    elif obj.OMERO_CLASS == 'ExperimenterGroup':
        # for some reason getOwner() et al. return nothing on a group, so we
        # simply put it to None for group objects:
        obj_dict['owner'] = None
    else:
        obj_dict['owner'] = obj.getOwnerOmeName()
    obj_dict['id'] = id_pfx + "%s:%s" % (obj.OMERO_CLASS, obj.getId())
    obj_dict['children'] = []
    return obj_dict


def gen_children(conn, id_str):
    """Get the children for a given node.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    id_str : str - OMERO object ID string (e.g. "G:23:Image:42")

    Returns
    =======
    list - a list of the child nodes dicts, having the 'load_on_demand'
           property set to True required by the jqTree JavaScript library
    """
    if id_str == 'ROOT':
        return gen_base_tree(conn)
    children = []
    _, gid, obj_type, oid = id_str.split(':')
    conn.SERVICE_OPTS.setOmeroGroup(gid)
    obj = conn.getObject(obj_type, oid)
    # we need different child-wrappers, depending on the object type:
    if obj_type == 'Experimenter':
        children_wrapper = conn.listProjects(oid)
    elif obj_type == 'ExperimenterGroup':
        children_wrapper = None  # FIXME
    else:
        children_wrapper = obj.listChildren()
    # now recurse into children:
    for child in children_wrapper:
        children.append(gen_obj_dict(child, 'G:' + gid + ':'))
    # set the on-demand flag unless the children are the last level:
    if not obj_type == 'Dataset':
        for child in children:
            child['load_on_demand'] = True
    return children


def gen_base_tree(conn):
    """Generate all group trees with their members as the basic tree.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway

    Returns
    =======
    base : a list of grouptree dicts
    """
    tree = []
    for group in conn.getGroupsMemberOf():
        tree.append(gen_group_tree(conn, group))
    return tree


def gen_group_tree(conn, group=None):
    """Create the tree nodes for a group and its members.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway

    Returns
    =======
    grouptree : a nested dict of a group (the default unless explicitly
                requested) and its members as a list of dicts in the 'children'
                item, starting with the current user as the first entry
    """
    if group is not None:
        conn.SERVICE_OPTS.setOmeroGroup(group.getId())
    else:
        group = conn.getGroupFromContext()
    gid = str(group.getId())
    group_dict = gen_obj_dict(group)
    # add the user's own tree first:
    user = conn.getUser()
    user_dict = gen_obj_dict(user, 'G:' + gid + ':')
    user_dict['load_on_demand'] = True
    group_dict['children'].append(user_dict)
    # then add the trees for other group members
    for user in conn.listColleagues():
        user_dict = gen_obj_dict(user, 'G:' + gid + ':')
        user_dict['load_on_demand'] = True
        group_dict['children'].append(user_dict)
    return group_dict


def check_credentials(conn):
    """Check if supplied credentials are valid.

    Parameters
    ==========
    conn : omero.gateway.BlitzGateway

    Returns
    =======
    conntected : bool
        True if connecting was successful (i.e. credentials are correct), False
        otherwise. In addition, a corresponding message is printed.
    """
    connected = conn.connect()
    if connected:
        print('Success logging into OMERO with user ID %s' % conn.getUserId())
    else:
        print('ERROR logging into OMERO.')
    return connected


def omero_to_hrm(conn, id_str, dest):
    """Download the corresponding original file(s) from an image ID.

    This works only for image ID's that were created with OMERO 5.0 or later as
    previous versions don't have an "original file" linked to an image.

    Note that files will be downloaded with their original name, which is not
    necessarily the name shown by OMERO, e.g. if an image name was changed in
    OMERO. To keep filesets consistent, we have to preserve the original names!

    In addition to the original file(s), it also downloads a thumbnail of the
    requested file from OMERO and puts it into the appropriate place so HRM
    will show it as a preview until the user hits "re-generate preview".

    Parameters
    ==========
    conn : omero.gateway.BlitzGateway
    id_str: str - the ID of the OMERO image (e.g. "G:23:Image:42")
    dest: str - destination directory

    Returns
    =======
    True in case the download was successful, False otherwise.
    """
    # FIXME: group switching required!!
    _, gid, obj_type, image_id = id_str.split(':')
    # check if dest is a directory, rewrite it otherwise:
    if not os.path.isdir(dest):
        dest = os.path.dirname(dest)
    from omero_model_OriginalFileI import OriginalFileI
    # use image objects and getFileset() methods to determine original files,
    # see the following OME forum thread for some more details:
    # https://www.openmicroscopy.org/community/viewtopic.php?f=6&t=7563
    image_obj = conn.getObject("Image", image_id)
    if not image_obj:
        print("ERROR: can't find image with ID %s!" % image_id)
        return False
    fset = image_obj.getFileset()
    if not fset:
        print("ERROR: no original file(s) for image %s found!" % image_id)
        return False
    # TODO I (issue #438): in case the query fails, this means most likely that
    # a file was uploaded in an older version of OMERO and therefore the
    # original file is not available. However, it was possible to upload with
    # the "archive" option, we should check if such archived files are
    # retrieved with the above query.
    # TODO II (issue #398): in case no archived file is available, we could
    # fall back to downloading the OME-TIFF instead.
    downloads = []
    # assemble a list of items to download, check if any files already exist:
    for fset_file in fset.listFiles():
        tgt = os.path.join(dest, fset_file.getName())
        if os.path.exists(tgt):
            print("ERROR: target file '%s' already existing!" % tgt)
            return False
        fset_id = fset_file.getId()
        downloads.append((fset_id, tgt))
    # now initiate the downloads for all original files:
    for (fset_id, tgt) in downloads:
        try:
            conn.c.download(OriginalFileI(fset_id), tgt)
        except:
            print("ERROR: downloading %s to '%s' failed!" % (fset_id, tgt))
            return False
        print("Downloaded original file %s to '%s'." % (fset_id, tgt))
    # NOTE: for filesets with a single file or e.g. ICS/IDS pairs it makes
    # sense to use the target name of the first file to construct the name for
    # the thumbnail, but it is unclear whether this is a universal approach:
    download_thumb(conn, image_id, downloads[0][1])
    return True


def download_thumb(conn, image_id, dest):
    """Download the thumbnail of a given image from OMERO.

    In case PIL (Python Imaging Library) is installed, download the thumbnail
    of a given OMERO image and place it as preview in the corresponding HRM
    directory.

    Parameters
    ==========
    conn : omero.gateway.BlitzGateway
    image_id: str - an OMERO object ID of an image (e.g. '102')
    dest: str - destination filename

    Returns
    =======
    True in case the download was successful, False otherwise.
    """
    try:
        import Image
        import StringIO
    except ImportError:
        return False
    image_obj = conn.getObject("Image", image_id)
    image_data = image_obj.getThumbnail()
    thumbnail = Image.open(StringIO.StringIO(image_data))
    base_dir, fname = os.path.split(dest)
    target = base_dir + "/hrm_previews/" + fname + ".preview_xy.jpg"
    try:
        thumbnail.save(target)
        # TODO: os.chown() to fix permissions, see #457!
        print("Downloaded thumbnail to '%s'." % target)
        return True
    except:
        print("ERROR downloading thumbnail to '%s'." % target)
        return False


def hrm_to_omero(conn, id_str, image_file):
    """Upload an image into a specific dataset in OMERO.

    In case we know from the suffix that a given file format is not supported
    by OMERO, the upload will not be initiated at all (e.g. for SVI-HDF5,
    having the suffix '.h5').

    Parameters
    ==========
    id_str: str - the ID of the target dataset in OMERO (e.g. "G:7:Dataset:23")
    image_file: str - the local image file including the full path

    Returns
    =======
    True in case of success, False otherwise.
    """
    if image_file.lower().endswith(('.h5', '.hdf5')):
        print 'ERROR: HDF5 files are not supported by OMERO!'
        return False
    # TODO I: group switching required!!
    _, gid, obj_type, dset_id = id_str.split(':')
    # we have to create the annotations *before* we actually upload the image
    # data itself and link them to the image during the upload - the other way
    # round is not possible right now as the CLI wrapper (see below) doesn't
    # expose the ID of the newly created object in OMERO (confirmed by J-M and
    # Sebastien on the 2015 OME Meeting):
    namespace = 'deconvolved.hrm'
    #### mime = 'text/plain'
    # extract the image basename without suffix:
    # TODO: is it [0-9a-f] or really [0-9a-z] as in the original PHP code?
    basename = re.sub(r'(_[0-9a-f]{13}_hrm)\..*', r'\1', image_file)
    comment = gen_parameter_summary(basename + '.parameters.txt')
    #### annotations = []
    #### # TODO: the list of suffixes should not be hardcoded here!
    #### for suffix in ['.hgsb', '.log.txt', '.parameters.txt']:
    ####     if not os.path.exists(basename + suffix):
    ####         continue
    ####     ann = conn.createFileAnnfromLocalFile(
    ####         basename + suffix, mimetype=mime, ns=namespace, desc=None)
    ####     annotations.append(ann.getId())
    # currently there is no direct "Python way" to import data into OMERO, so
    # we have to use the CLI wrapper for this:
    from omero.cli import CLI
    cli = CLI()
    cli.loadplugins()
    # NOTE: cli._client should be replaced with cli.set_client() when switching
    # to support for OMERO 5.1 and later only:
    cli._client = conn.c
    import_args = ["import"]
    import_args.extend(['-d', dset_id])
    if comment is not None:
        import_args.extend(['--annotation_ns', namespace])
        import_args.extend(['--annotation_text', comment])
    #### for ann_id in annotations:
    ####     import_args.extend(['--annotation_link', str(ann_id)])
    import_args.append(image_file)
    # print("import_args: " + str(import_args))
    try:
        cli.invoke(import_args, strict=True)
    except:
        print 'ERROR: uploading to OMERO failed! ' + str(import_args)
        return False
    return True


def gen_parameter_summary(fname):
    """Generate a parameter summary from the HRM-generated HTML file.

    Parse the HTML file generated by the HRM containing the parameter summary
    and generate a plain-text version of it. The HTML file is assumed to
    contain three <table> items that contain a single <tr> column with the
    table title in the first row, the second row is ignored (column legend) and
    the actual parameters in four columns in each of the subsequent rows, e.g.
    something of this form:

    ___________________________________________
    |___________________title_________________|
    |_________________(ignored)_______________|
    | parameter | channel | (ignored) | value |
    ...
    | parameter | channel | (ignored) | value |
    -------------------------------------------

    Parameters
    ==========
    fname : str - the filename of the HTML parameter summary

    Returns
    =======
    str - the formatted string containing the parameter summary
    """
    try:
        from bs4 import BeautifulSoup
    except ImportError:
        try:
            from BeautifulSoup import BeautifulSoup
        except ImportError:
            return """This file was imported via the HRM-OMERO connector.
                   For a parameter summary, the 'BeautifulSoup' package for
                   Python is required at import time on the HRM system.
                   """
    try:
        soup = BeautifulSoup(open(fname, 'r'))
    except IOError:
        return None
    summary = ''
    for table in soup.findAll('table'):
        rows = table.findAll('tr')
        # the table header:
        summary += "%s\n" % rows[0].findAll('td')[0].text
        summary += "==============================\n"
        # and the table body:
        for row in rows[2:]:
            cols = row.findAll('td')
            summary += "%s [Ch: %s]: %s\n" % (
                cols[0].text.replace('&mu;m', 'um').replace(u'\u03bc', 'u'),
                cols[1].text,
                cols[3].text)
        summary += '\n'
    return summary


def bool_to_exitstatus(value):
    """Convert a boolean to a POSIX process exit code.

    As boolean values in Python are a subset of int, True will be converted to
    the int value '1', which is the opposite of a successful process return
    code on POSIX systems. Therefore, we simply invert the boolean value to
    turn it into a proper exit code.
    """
    if type(value) is bool:
        return not value
    else:
        return value


def parse_arguments():
    """Parse the commandline arguments."""
    argparser = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter
    )
    argparser.add_argument(
        '-v', '--verbose', dest='verbosity', action='count', default=0,
        help='verbose messages (repeat for more details)')

    # required arguments group
    req_args = argparser.add_argument_group(
        'required arguments', 'NOTE: MUST be given before any subcommand!')
    req_args.add_argument(
        '-u', '--user', required=True, help='OMERO username')
    req_args.add_argument(
        '-w', '--password', required=True, help='OMERO password')

    subparsers = argparser.add_subparsers(
        help='.', dest='action',
        description='Action to be performed, one of the following:')

    # checkCredentials parser
    subparsers.add_parser(
        'checkCredentials', help='check if login credentials are valid')

    # retrieveChildren parser
    parser_subtree = subparsers.add_parser(
        'retrieveChildren',
        help="get the children of a given node object (JSON)")
    parser_subtree.add_argument(
        '--id', type=str, required=True,
        help='ID string of the object to get the children for, e.g. "User:23"')

    # OMEROtoHRM parser
    parser_o2h = subparsers.add_parser(
        'OMEROtoHRM', help='download an image from the OMERO server')
    parser_o2h.add_argument(
        '-i', '--imageid', required=True,
        help='the OMERO ID of the image to download, e.g. "Image:42"')
    parser_o2h.add_argument(
        '-d', '--dest', type=str, required=True,
        help='the destination directory where to put the downloaded file')

    # HRMtoOMERO parser
    parser_h2o = subparsers.add_parser(
        'HRMtoOMERO', help='upload an image to the OMERO server')
    parser_h2o.add_argument(
        '-d', '--dset', required=True, dest='dset',
        help='the ID of the target dataset in OMERO, e.g. "Dataset:23"')
    parser_h2o.add_argument(
        '-f', '--file', type=str, required=True,
        help='the image file to upload, including the full path')
    parser_h2o.add_argument(
        '-n', '--name', type=str, required=False,
        help='a label to use for the image in OMERO')
    parser_h2o.add_argument(
        '-a', '--ann', type=str, required=False,
        help='annotation text to be added to the image in OMERO')

    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def main():
    """Parse commandline arguments and initiate the requested tasks."""
    args = parse_arguments()

    conn = omero_login(args.user, args.password, HOST, PORT)

    # TODO: implement requesting groups via cmdline option

    if args.action == 'checkCredentials':
        return check_credentials(conn)
    elif args.action == 'retrieveChildren':
        return print_children_json(conn, args.id)
    elif args.action == 'OMEROtoHRM':
        return omero_to_hrm(conn, args.imageid, args.dest)
    elif args.action == 'HRMtoOMERO':
        return hrm_to_omero(conn, args.dset, args.file)
    else:
        raise Exception('Huh, how could this happen?!')


if __name__ == "__main__":
    sys.exit(bool_to_exitstatus(main()))
