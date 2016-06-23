#!/usr/bin/env python
# -*- coding: utf-8 -*-#
#

import time
import re

def write_micr_warning(job_output):
    pattern = '(.*){Microscope conflict for channel([0 - 9]): (. *)}(.*)'
    matchObj = re.match(pattern, job_output, re.S)


def write_scaling_factors(job_output):
    pattern = '(.*){Scaling of channel ([0-9]): (.*)}}(.*)'
    matchObj = re.match(pattern, job_output, re.S)


def write_img_params(job_output):
    pattern  = '(.*){Parameter ([a-zA-Z3]+?) (of channel ([0-9])\s|)(.*) '
    pattern += '(template|metadata|meta data): (.*).}}(.*)'
    matchObj = re.match(pattern, job_output, re.S)


def write_resto_params(job_output):
    restParams = ['algorithm', 'iterations', 'quality change threshold',
                    'format', 'background removed', 'estimation',
                  'ratio', 'autocrop', 'stabilization']

    for paramName in restParams:
        pattern  = '(.*)%s: ' % paramName
        pattern += '([0-9\.\s\,]+|[a-zA-Z0-9\,\s\/]+\s?[a-zA-Z0-9]*)\n(.*)'
        matchObj = re.match(pattern, job_output, re.S|re.I)


def hureport_2html(job_output):
    start = time.clock()

    write_micr_warning(job_output)
    write_scaling_factors(job_output)
    write_img_params(job_output)
    write_resto_params(job_output)

    end = time.clock()
    print(end - start)


def filter_huygens_output(job_output):
    hureport_2html(job_output)


def main():
    path = "../scheduler_client0.log"

    with open(path, 'r') as content_file:
        content = content_file.read()

    filter_huygens_output(content)


main()

