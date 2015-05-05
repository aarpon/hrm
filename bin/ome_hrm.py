#!/usr/bin/env python

"""OMERO connector for the Huygens Remote Manager (HRM).

This wrapper processes all requests from the HRM web interface to communicate
to an OMERO server for listing available images, transferring data, etc.
"""


import os
from omero.gateway import BlitzGateway

# the default connection values
HOST = 'omero.mynetwork.xy'
PORT = 4064
USERNAME = 'foo'
PASSWORD = 'bar'

# allow overriding the default values
if "OMERO_HOST" in os.environ:
    HOST=os.environ['OMERO_HOST']
if "OMERO_PORT" in os.environ:
    PORT=os.environ['OMERO_PORT']
if "OMERO_USER" in os.environ:
    USERNAME=os.environ['OMERO_USER']
if "OMERO_PASS" in os.environ:
    PASSWORD=os.environ['OMERO_PASS']


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


def gen_obj_dict(obj):
    """Create a dict from an OMERO object."""
    obj_dict = {}
    obj_dict['id'] = obj.getId()
    obj_dict['name'] = obj.getName()
    obj_dict['owner'] = obj.getOwnerOmeName()
    obj_dict['children'] = []
    return obj_dict


def gen_image_dict(image):
    """Create a dict from an OMERO image."""
    if image.OMERO_CLASS is not 'Image':
        raise ValueError
    image_dict = {}
    image_dict['id'] = image.getId()
    image_dict['name'] = image.getName()
    image_dict['owner'] = image.getOwnerOmeName()
    return image_dict


def gen_xml_tree(obj_tree):
    """Generate (print) an XML tree from the OMERO objects."""
    for proj in obj_tree:
        iprint('<Project>%s<id>%s</id>' % (proj['name'], proj['id']))
        for dset in proj['children']:
            iprint('<Dataset>%s<id>%s</id>' % (dset['name'], dset['id']), 4)
            for img in dset['children']:
                iprint('<Image>%s<id>%s</id>' % (img['name'], img['id']), 8)
                iprint('</Image>', 8)
            iprint('</Dataset>', 4)
        iprint('</Project>')


conn = BlitzGateway(USERNAME, PASSWORD, host=HOST, port=PORT)
connected = conn.connect()

print('Connected? -> %s' % connected)

user = conn.getUser()
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


obj_tree = []
#for project in conn.listProjects(my_expId):
for project in conn.listProjects():
        proj_dict = gen_obj_dict(project)
        for dataset in project.listChildren():
                dset_dict = gen_obj_dict(dataset)
                for image in dataset.listChildren():
                        dset_dict['children'].append(gen_image_dict(image))
                proj_dict['children'].append(dset_dict)
        obj_tree.append(proj_dict)

gen_xml_tree(obj_tree)
