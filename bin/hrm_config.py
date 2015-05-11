#!/usr/bin/env python

"""Helper module to parse the HRM (shell style) config file.

Usually, the config is located at /etc/hrm.conf and written in shell syntax as
this file simply gets sourced by the bash init script and other shell based
tools.

This module is not meant to be executed directly and doesn't do anything in
this case.
"""

import shlex
import sys


def parse_hrm_conf(filename):
    """Assemble a dict from the HRM config file (shell syntax)."""
    config = dict()
    body = file(filename, 'r').read()
    lexer = shlex.shlex(body)
    lexer.wordchars += '-./'
    while True:
        token = lexer.get_token()
        if token is None or token == '':
            break
        # we assume entries of the following form:
        # KEY="some-value"
        key = token
        assert lexer.get_token() == '='
        value = lexer.get_token()
        value = value.replace('"', '')  # remove double quotes
        value = value.replace("'", '')  # remove single quotes
        config[key] = value
    return config


def check_hrm_conf(config):
    """Check the config dict for required entries."""
    required = ['OMERO_PKG', 'OMERO_HOSTNAME']
    for entry in required:
        if entry not in config:
            raise SyntaxError('Missing "%s" in the HRM config file.' % entry)


if __name__ == "__main__":
    print __doc__
    sys.exit(1)

CONFIG = parse_hrm_conf('/etc/hrm.conf')
check_hrm_conf(CONFIG)
