#!/usr/bin/env python

"""OMERO connector for the Huygens Remote Manager (HRM).

This wrapper processes all requests from the HRM web interface to communicate
to an OMERO server for listing available images, transferring data, etc.
"""

# pylint: disable=superfluous-parens

# TODO:
# - trees for different groups
# - generate sub-trees (to update and/or populate on demand)
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


def get_group_tree_json(conn, group):
    """Generates the group tree and returns it in JSON format."""
    # TODO: this is probably also required for a user's sub-tree
    # we're currently only having a single tree (dict), but jqTree expects a
    # list of dicts, so we have to encapsulate it in [] for now:
    print(tree_to_json([gen_group_tree(conn, group)]))


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
    # TODO: it's probably better to store the owner's ID instead of the name
    obj_dict['owner'] = obj.getOwnerOmeName()
    obj_dict['class'] = obj.OMERO_CLASS
    obj_dict['children'] = []
    return obj_dict


def gen_proj_tree(conn, user_obj):
    """Create a list of project trees for a user.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    user_obj : omero.gateway._ExperimenterWrapper
    """
    proj_tree = []
    for project in conn.listProjects(user_obj.getId()):
        proj_dict = gen_obj_dict(project)
        for dataset in project.listChildren():
            dset_dict = gen_obj_dict(dataset)
            for image in dataset.listChildren():
                dset_dict['children'].append(gen_obj_dict(image))
            proj_dict['children'].append(dset_dict)
        proj_tree.append(proj_dict)
    return proj_tree


def gen_user_dict(user_obj):
    """Create a dict from an OMERO user.

    Structure
    =========
    {
        'children': [],
        'id': 'Experimenter:1154',
        'label': 'demo user',
        'ome_name': u'demo01',
        'class': 'Experimenter'
    }
    """
    user_dict = dict()
    user_dict['id'] = "%s:%s" % (user_obj.OMERO_CLASS, user_obj.getId())
    user_dict['label'] = user_obj.getFullName()
    user_dict['ome_name'] = user_obj.getName()
    user_dict['class'] = user_obj.OMERO_CLASS
    user_dict['children'] = []
    return user_dict


def gen_user_tree(conn, user_obj):
    """Create a tree with user information and corresponding projects.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    user_obj : omero.gateway._ExperimenterWrapper

    Returns
    =======
    {
        "id": (int, e.g. 14),
        "label": (str, e.g. "01 Demouser"),
        "ome_name": (str, e.g. "demo01"),
        "children": proj_tree (list)
    }
    """
    user_dict = gen_user_dict(user_obj)
    user_dict['children'] = gen_proj_tree(conn, user_obj)
    return user_dict


def gen_group_tree(conn, group_obj):
    """Create a tree for a group with all user subtrees.

    Parameters
    ==========
    conn : omero.gateway._BlitzGateway
    group_obj : omero.gateway._ExperimenterGroupWrapper

    Returns
    =======
    {
        "id": (int, e.g. 9),
        "label": (str, e.g. "Sandbox Lab"),
        "children": user_trees (list of dict))
    }
    """
    group_dict = dict()
    group_dict['id'] = group_obj.getId()
    group_dict['label'] = group_obj.getName()
    group_dict['description'] = group_obj.getDescription()
    group_dict['class'] = group_obj.OMERO_CLASS
    group_dict['children'] = []
    # add the user's own tree first:
    user_obj = conn.getUser()
    user_tree = gen_user_tree(conn, user_obj)
    group_dict['children'].append(user_tree)
    # then add the trees for other group members
    for colleague in conn.listColleagues():
        user_tree = gen_user_tree(conn, colleague)
        group_dict['children'].append(user_tree)
    return group_dict


def check_credentials(conn):
    """Check if supplied credentials are valid."""
    # TODO: do we really need this function...?
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
    # print('Download complete.')


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
    #### # extract the image basename without suffix:
    #### basename = re.sub(r'(_[0-9a-f]{13}_hrm)\..*', r'\1', image_file)
    #### annotations = []
    #### # TODO: the list of suffixes should not be hardcoded here!
    #### for suffix in ['.hgsb', '.log.txt', '.parameters.txt']:
    ####     if not os.path.exists(basename + suffix):
    ####         continue
    ####     ann = conn.createFileAnnfromLocalFile(
    ####         basename + suffix, mimetype="text/plain", ns=namespace, desc=None)
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

    # if not requested other, we're just using the default group
    group = conn.getGroupFromContext()
    # TODO: implement requesting groups via cmdline option

    if args.action == 'checkCredentials':
        check_credentials(conn)
    elif args.action == 'retrieveUserTree':
        get_group_tree_json(conn, group)
    elif args.action == 'OMEROtoHRM':
        omero_to_hrm(conn, args.imageid, args.dest)
    elif args.action == 'HRMtoOMERO':
        hrm_to_omero(conn, args.dset, args.file)
    else:
        raise Exception('Huh, how could this happen?!')


if __name__ == "__main__":
    sys.exit(main())
