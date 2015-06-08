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


import sys
import argparse
import os
import json
import re
import hrm_config

# put OMERO into our PYTHONPATH:
OMERO_LIB = '%s/lib/python' % hrm_config.CONFIG['OMERO_PKG']
sys.path.insert(0, OMERO_LIB)
from omero.gateway import BlitzGateway

# the connection values
HOST = hrm_config.CONFIG['OMERO_HOSTNAME']
if 'OMERO_PORT' in hrm_config.CONFIG:
    PORT = hrm_config.CONFIG['OMERO_PORT']
else:
    PORT = 4064


def omero_login(user, passwd, host, port):
    """Establish the connection to an OMERO server."""
    conn = BlitzGateway(user, passwd, host=host, port=port)
    conn.connect()
    return conn


def tree_to_json(obj_tree):
    """Create a JSON object with a given format from a tree."""
    return json.dumps(obj_tree, sort_keys=True,
                      indent=4, separators=(',', ': '))


def get_children_json(conn, id_str):
    """Gets the child nodes of the given ID and returns them in JSON format."""
    print(tree_to_json(gen_children(conn, id_str)))


def get_group_tree_json(conn, group=None):
    """Generates the group tree and returns it in JSON format."""
    # we're currently only having a single tree (dict), but jqTree expects a
    # list of dicts, so we have to encapsulate it in [] for now:
    print(tree_to_json([gen_group_tree(conn, group)]))


def get_obj_tree_json(conn, obj_id, levels=0):
    """Generates the group tree and returns it in JSON format."""
    print(tree_to_json([gen_obj_tree(conn, obj_id, levels)]))


def gen_obj_dict(obj):
    """Create a dict from an OMERO object.

    Structure
    =========
    {
        'children': [],
        'id': 'Project:1154',
        'label': 'HRM_TESTDATA',
        'owner': u'demo01',
        'class': 'Project'
    }
    """
    obj_dict = dict()
    obj_dict['id'] = "%s:%s" % (obj.OMERO_CLASS, obj.getId())
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
    obj_dict['children'] = []
    return obj_dict


def gen_children(conn, id_str):
    """Get the children for a given node."""
    obj_type = id_str.split(':')[0]
    tree = gen_obj_tree(conn, id_str, levels=1)
    if not obj_type == 'Dataset':
        for child in tree['children']:
            child['load_on_demand'] = True
    return tree['children']


def gen_obj_tree(conn, obj_id, levels=0):
    """Create a subtree of a given ID."""
    obj_type, oid = obj_id.split(':')
    obj = conn.getObject(obj_type, oid)
    obj_tree = gen_obj_dict(obj)
    if obj_type == 'Image':
        # the lowest level of our tree, so we don't recurse any further:
        levels = 0
    if levels == 0:
        return obj_tree
    # we need different child-wrappers, depending on the object type:
    if obj_type == 'Experimenter':
        children_wrapper = conn.listProjects(oid)
    elif obj_type == 'ExperimenterGroup':
        children_wrapper = None  # FIXME
    else:
        children_wrapper = obj.listChildren()
    # now recurse into children:
    for child in children_wrapper:
        cid = child.OMERO_CLASS + ':' + str(child.getId())
        child_tree = gen_obj_tree(conn, cid, levels - 1)
        obj_tree['children'].append(child_tree)
    return obj_tree


def gen_group_tree(conn, group=None):
    """Create a tree for a group with all user subtrees.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    group : omero.gateway._ExperimenterGroupWrapper

    Returns
    =======
    {
        "id": (int, e.g. 9),
        "label": (str, e.g. "Sandbox Lab"),
        "children": user_trees (list of dict))
    }
    """
    if group is None:
        group = conn.getGroupFromContext()
    group_dict = gen_obj_dict(group)
    # add the user's own tree first:
    user = conn.getUser()
    cid = user.OMERO_CLASS + ':' + str(user.getId())
    group_dict['children'].append(gen_obj_tree(conn, cid, levels=-1))
    # then add the trees for other group members
    for user in conn.listColleagues():
        cid = user.OMERO_CLASS + ':' + str(user.getId())
        group_dict['children'].append(gen_obj_tree(conn, cid, levels=-1))
    return group_dict


def check_credentials(conn):
    """Check if supplied credentials are valid."""
    connected = conn.connect()
    if connected:
        print('Success logging into OMERO with user ID %s' % conn.getUserId())
    else:
        print('ERROR logging into OMERO.')
    return connected


def omero_to_hrm(conn, image_id, dest):
    """Download the corresponding original file from an image ID.

    This works only for image ID's that were created with OMERO 5.0 or later as
    previous versions don't have an "original file" linked to an image.

    In addition to the original file, it also downloads a thumbnail of the
    requested file from OMERO and puts it into the appropriate place so HRM
    will show it as a preview until the user hits "re-generate preview".

    Parameters
    ==========
    image_id: str - OMERO image ID (e.g. "Image:42")
    dest: str - destination filename (incl. path)

    TODO
    ====
    we should check if older ones could have such a file if they were uploaded
    with the "archive" option.
    """
    # get the numeric ID from the combined string:
    image_id = image_id.split(':')[1]
    # as we're downloading original files, we need to crop away the additional
    # suffix that OMERO adds to the name in case the image belongs to a
    # fileset, enclosed in rectangular brackets "[...]", e.g. the file with the
    # OMERO name "foo.lsm [foo #2]" should become "foo.lsm"
    dest = re.sub(r' \[[^[]*\]$', '', dest)
    if os.path.exists(dest):
        raise IOError('target file "%s" already existing!' % dest)
    from omero.rtypes import unwrap
    from omero.sys import ParametersI
    from omero_model_OriginalFileI import OriginalFileI
    query = conn.c.getSession().getQueryService()
    params = ParametersI()
    params.addLong('iid', image_id)
    sql = "select f from Image i" \
        " left outer join i.fileset as fs" \
        " join fs.usedFiles as uf" \
        " join uf.originalFile as f" \
        " where i.id = :iid"
    query_res = query.projection(sql, params, {'omero.group': '-1'})
    file_id = unwrap(query_res[0])[0].id.val
    # print('Downloading original file with ID: %s' % file_id)
    orig_file = OriginalFileI(file_id)
    conn.c.download(orig_file, dest)
    download_thumb(conn, image_id, dest)
    # print('Download complete.')


def download_thumb(conn, image_id, dest):
    """Download the thumbnail of a given image from OMERO."""
    # in case PIL is installed, download the thumbnail as a preview:
    try:
        import Image
        import StringIO
    except ImportError:
        return
    image = conn.getObject("Image", image_id)
    img_data = image.getThumbnail()
    thumbnail = Image.open(StringIO.StringIO(img_data))
    tgt, name = os.path.split(dest)
    thumbnail.save(tgt + "/hrm_previews/" + name + ".preview_xy.jpg")


def hrm_to_omero(conn, dset_id, image_file):
    """Upload an image into a specific dataset in OMERO.

    Parameters
    ==========
    dset_id: str - the ID of the target dataset in OMERO (e.g. "Dataset:23")
    image_file: str - the local image file including the full path
    """
    # we have to create the annotations *before* we actually upload the image
    # data itself and link them to the image during the upload - the other way
    # round is not possible right now as the CLI wrapper (see below) doesn't
    # expose the ID of the newly created object in OMERO (confirmed by J-M and
    # Sebastien on the 2015 OME Meeting):
    #### namespace = 'deconvolved.hrm'
    #### mime = 'text/plain'
    #### # extract the image basename without suffix:
    #### basename = re.sub(r'(_[0-9a-f]{13}_hrm)\..*', r'\1', image_file)
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
    import_args.extend(['-d', dset_id.split(':')[1]])
    #### for ann_id in annotations:
    ####     import_args.extend(['--annotation_link', str(ann_id)])
    import_args.append(image_file)
    # print("import_args: " + str(import_args))
    cli.invoke(import_args)


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

    # retrieveSubTree parser
    parser_subtree = subparsers.add_parser(
        'retrieveSubTree',
        help="get a subtree of a given object (JSON)")
    parser_subtree.add_argument(
        '--id', type=str, required=True,
        help='ID string of the object to build a subtree for, e.g. "User:23"')
    parser_subtree.add_argument(
        '--levels', type=int, default=-1,
        help='number of tree levels to generate (-1 for all)')

    # retrieveUserTree parser
    parser_tree = subparsers.add_parser(
        'retrieveUserTree',
        help="get a user's Projects/Datasets/Images tree (JSON)")
    parser_tree.add_argument(
        '--allmembers', type=bool, default=False,
        help='build tree for all members in the current group')

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
        check_credentials(conn)
    elif args.action == 'retrieveChildren':
        get_children_json(conn, args.id)
    elif args.action == 'retrieveUserTree':
        get_group_tree_json(conn)
    elif args.action == 'retrieveSubTree':
        get_obj_tree_json(conn, args.id, levels=args.levels)
    elif args.action == 'OMEROtoHRM':
        omero_to_hrm(conn, args.imageid, args.dest)
    elif args.action == 'HRMtoOMERO':
        hrm_to_omero(conn, args.dset, args.file)
    else:
        raise Exception('Huh, how could this happen?!')


if __name__ == "__main__":
    sys.exit(main())
