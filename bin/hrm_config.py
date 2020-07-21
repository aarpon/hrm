#!/usr/bin/env python

"""Helper module to parse the HRM (shell style) config file.

Usually, the config is located at /etc/hrm.conf and written in shell syntax as
this file simply gets sourced by the bash init script and other shell based
tools.

This module is not meant to be executed directly and doesn't do anything in
this case.
"""

# NOTE:
# It might be worth checking out the solution described on stackoverflow [1]
# using an approach based on a real shell subprocess like this:
"""
>>> command = ['bash', '-c', 'source init_env && env']
>>> proc = subprocess.Popen(command, stdout = subprocess.PIPE)
>>> for line in proc.stdout:
...    (key, _, value) = line.partition("=")
...    os.environ[key] = value
>>> proc.communicate()
"""
# [1]: http://stackoverflow.com/questions/3503719/


import shlex
import sys
import logging


def parse_hrm_conf(filename):
    """Assemble a dict from the HRM config file (shell syntax).

    Parameters
    ==========
    filename: str  - the filename to parse

    Returns
    =======
    config: dict

    Example
    =======
    {
        'HRM_DATA': '/export/hrm_data',
        'HRM_DEST': 'dst',
        'HRM_HOME': '/var/www/hrm',
        'HRM_LOG': '/var/log/hrm',
        'HRM_SOURCE': 'src',
        'OMERO_HOSTNAME': 'omero.mynetwork.xy',
        'OMERO_PKG': '/opt/OMERO/OMERO.server',
        'OMERO_PORT': '4064',
        'PHP_CLI': '/usr/local/php/bin/php',
        'SUSER': 'hrm'
    }
    """
    config = dict()
    body = file(filename, 'r').read()
    lexer = shlex.shlex(body)
    lexer.wordchars += '-./'
    while True:
        token = lexer.get_token()
        if token is None or token == '':
            break
        # it's valid sh syntax to use a semicolon to join lines, so accept it:
        if token == ';':
            continue
        # we assume entries of the following form:
        # KEY="some-value"
        key = token
        try:
            equals = lexer.get_token()
            assert equals == '='
        except AssertionError:
            raise SyntaxError(
                "Can't parse %s, invalid syntax in line %s "
                "(expected '=', found '%s')." %
                (filename, lexer.lineno, equals))
        except Exception as err:
                logging.warn('Error parsing config: %s', err)
        value = lexer.get_token()
        value = value.replace('"', '')  # remove double quotes
        value = value.replace("'", '')  # remove single quotes
        config[key] = value
    logging.debug('Successfully parsed [%s].', filename)
    return config


def check_hrm_conf(config):
    """Check the config dict for required entries."""
    required = ['OMERO_PKG', 'OMERO_HOSTNAME']
    for entry in required:
        if entry not in config:
            raise SyntaxError('Missing "%s" in the HRM config file.' % entry)
    logging.debug('HRM config file passed all checks.')


if __name__ == "__main__":
    print __doc__
    sys.exit(1)

CONFIG = parse_hrm_conf('/etc/hrm.conf')
check_hrm_conf(CONFIG)
