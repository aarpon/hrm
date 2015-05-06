#!/usr/bin/env python

"""OMERO connector for the Huygens Remote Manager (HRM).

This wrapper processes all requests from the HRM web interface to communicate
to an OMERO server for listing available images, transferring data, etc.
"""


import sys
import argparse
import os
from omero.gateway import BlitzGateway
import json


# possible actions (this will be used when showing the help message on the
# command line later on as well, so keep this in mind when formatting!)
ACTIONS = """actions:
  checkCredentials      Check if login credentials are valid.
  retrieveUserTree      Get a user's Projects/Datasets/Images tree (JSON).
  OMEROtoHRM            Download an image from the OMERO server.
  HRMtoOMERO            Upload an image to the OMERO server.
"""


# the default connection values
HOST = 'omero.mynetwork.xy'
PORT = 4064
USERNAME = 'foo'
PASSWORD = 'bar'

# allow overriding the default values
if "OMERO_HOST" in os.environ:
    HOST = os.environ['OMERO_HOST']
if "OMERO_PORT" in os.environ:
    PORT = os.environ['OMERO_PORT']
if "OMERO_USER" in os.environ:
    USERNAME = os.environ['OMERO_USER']
if "OMERO_PASS" in os.environ:
    PASSWORD = os.environ['OMERO_PASS']


def log(text):
    """Helper function to prepare proper logging later."""
    iprint(text)


def iprint(text, indent=0):
    """Helper method for intented printing."""
    print('%s%s' % (" " * indent, text))


def print_obj(obj, indent=0):
    """Helper method to display info about OMERO objects.

    Not all objects will have a "name" or owner field.
    """
    print """%s%s:%s  Name:"%s" (owner=%s)""" % (
        " " * indent,
        obj.OMERO_CLASS,
        obj.getId(),
        obj.getName(),
        obj.getOwnerOmeName())


def gen_xml_info_header(conn):
    # TODO: has to be converted to sth producing a dict with connection infos
    user = conn.getUser()
    iprint('<!-- ==== OMERO user information ====')
    print "Current user:"
    print "   ID:", user.getId()
    print "   Username:", user.getName()
    print "   Full Name:", user.getFullName()

    for g in conn.getGroupsMemberOf():
        print "   ID:", g.getName(), " Name:", g.getId()

    group = conn.getGroupFromContext()
    print "Current group: ", group.getName()

    my_expId = conn.getUser().getId()
    print("Experimenter ID: %s" % my_expId)
    iprint('==== OMERO user information ==== -->')


def omero_login():
    # log('Trying to log into OMERO.')
    conn = BlitzGateway(USERNAME, PASSWORD, host=HOST, port=PORT)
    conn.connect()
    user = conn.getUser()
    # log('OMERO user ID for username %s: %s' % (user.getName(), user.getId()))
    return conn


def retrieve_user_tree():
    # obj_tree = gen_proj_tree()
    obj_tree = gen_group_tree()
    print(json.dumps(obj_tree, sort_keys=True,
        indent=4, separators=(',', ': ')))


def gen_obj_dict(obj):
    """Create a dict from an OMERO object.

    Structure
    =========
    {
        'children': [],
        'id': 1154L,
        'label': 'HRM_TESTDATA',
        'owner': u'demo01',
        'class': 'Project'
    }
    """
    obj_dict = dict()
    obj_dict['id'] = obj.getId()
    obj_dict['label'] = obj.getName()
    # TODO: it's probably better to store the owner's ID instead of the name
    obj_dict['owner'] = obj.getOwnerOmeName()
    obj_dict['class'] = obj.OMERO_CLASS
    obj_dict['children'] = []
    return obj_dict


def gen_image_dict(image):
    """Create a dict from an OMERO image.

    Structure
    =========
    {'id': 1755L, 'label': 'Rot-13x-zstack.tif', 'owner': u'demo01'}
    """
    if image.OMERO_CLASS is not 'Image':
        raise ValueError
    image_dict = dict()
    image_dict['id'] = image.getId()
    image_dict['label'] = image.getName()
    # TODO: it's probably better to store the owner's ID instead of the name
    image_dict['owner'] = image.getOwnerOmeName()
    return image_dict


def gen_proj_tree(conn=None,uid=None):
    if conn is None:
        conn = omero_login()
    if uid is None:
        uid = conn.getUserId()
    obj_tree = []
    for project in conn.listProjects(uid):
        proj_dict = gen_obj_dict(project)
        for dataset in project.listChildren():
            dset_dict = gen_obj_dict(dataset)
            for image in dataset.listChildren():
                dset_dict['children'].append(gen_image_dict(image))
            proj_dict['children'].append(dset_dict)
        obj_tree.append(proj_dict)
    return obj_tree


def gen_user_tree(conn, user_obj):
    user_dict = dict()
    uid = user_obj.getId()
    user_dict['id'] = uid
    user_dict['label'] = user_obj.getFullName()
    user_dict['ome_name'] = user_obj.getName()
    user_dict['children'] = gen_proj_tree(conn, uid)
    return user_dict


def gen_group_tree():
    conn = omero_login()
    obj_tree = []
    group_obj = conn.getGroupFromContext()
    group_dict = dict()
    group_dict['id'] = group_obj.getId()
    group_dict['label'] = group_obj.getName()
    group_dict['description'] = group_obj.getDescription()
    group_dict['children'] = []

    user_obj = conn.getUser()
    user_tree = gen_user_tree(conn, user_obj)
    group_dict['children'].append(user_tree)
    obj_tree.append(group_dict)

    # TODO: add trees (or stubs) for other group members

    return obj_tree



def parse_arguments():
    """Parse the commandline arguments."""
    argparser = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=ACTIONS
    )
    argparser.add_argument('action', choices=['checkCredentials',
        'retrieveUserTree', 'OMEROtoHRM', 'HRMtoOMERO'],
        help='Action to be performed by the connector, see below for details.')
    argparser.add_argument('-u', '--user', required=True,
        help='OMERO username')
    argparser.add_argument('-w', '--password', required=True,
        help='OMERO password')
    argparser.add_argument('-v', '--verbose', dest='verbosity',
        action='count', default=0, help='verbosity (repeat for more details)')
    try:
        return argparser.parse_args()
    except IOError as err:
        argparser.error(str(err))


def main():
    """Parse commandline arguments and initiate the requested tasks."""
    # create a dict with the functions to call
    action_methods = {
        'checkCredentials': omero_login,
        'retrieveUserTree': retrieve_user_tree,
        'OMEROtoHRM': iprint,
        'HRMtoOMERO': iprint
    }

    args = parse_arguments()
    action_methods[args.action]()


if __name__ == "__main__":
    sys.exit(main())
