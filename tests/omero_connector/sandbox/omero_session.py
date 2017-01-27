#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys
import omero
import omero.cli

HOST = "yourhost"
USER = "youruser"
PASS = "yourpassword"

client = omero.client(HOST)
client.createSession(USER, PASS)

try:
    cli = omero.cli.CLI()
    cli.loadplugins()
    cli._client = client

    for x in sys.argv[1:]:
        cli.invoke(
            ["hql", "-q", "--limit", "-1",
            ("SELECT i.id FROM Experimenter i "
            "WHERE i.omeName='%s'") % x])

finally:
    client.__del__()

