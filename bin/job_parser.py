#!/usr/bin/env python
# -*- coding: utf-8 -*-#
#

import time
import re

class HuOutput2Html():
    def __init__(self, path=0):
        self.path = path


    def __del__(self):
        print "Destroy class instance"


    def write_micr_warning(self,job_output):
        pattern = '(.*){Microscope conflict for channel([0 - 9]): (. *)}(.*)'
        matchObj = re.match(pattern, job_output, re.S)


    def write_scaling_factors(self,job_output):
        pattern = '(.*){Scaling of channel ([0-9]): (.*)}}(.*)'
        matchObj = re.match(pattern, job_output, re.S)


    def write_img_params(self,job_output):
        pattern  = '(.*){Parameter ([a-zA-Z3]+?) (of channel ([0-9])\s|)(.*) '
        pattern += '(template|metadata|meta data): (.*).}}(.*)'
        matchObj = re.match(pattern, job_output, re.S)


    def write_resto_params(self,job_output):
        restParams = ['algorithm', 'iterations', 'quality change threshold',
                        'format', 'background removed', 'estimation',
                      'ratio', 'autocrop', 'stabilization']

        for paramName in restParams:
            pattern  = '(.*)%s: ' % paramName
            pattern += '([0-9\.\s\,]+|[a-zA-Z0-9\,\s\/]+\s?[a-zA-Z0-9]*)\n(.*)'
            matchObj = re.match(pattern, job_output, re.S|re.I)


    def write_coloc_tables(self,job_output):
        pattern  = '(.*){Colocalization report: (.*)}}}(.*)'
        matchObj = re.match(pattern, job_output, re.S)

        if matchObj:
            print "Found colocalization results: ", matchObj.group()


    def gen_html_output(self,job_output):
        start = time.clock()

        self.write_micr_warning(job_output)
        self.write_scaling_factors(job_output)
        self.write_img_params(job_output)
        self.write_resto_params(job_output)
        self.write_coloc_tables(job_output)

        end = time.clock()
        print(end - start)


    def process(self):
        if self.path == 0:
            self.path = "/home/daniel/Downloads/hrm_results_2016-06-23_160049/scheduler_client0.log"

        with open(self.path, 'r') as content_file:
            content = content_file.read()

        self.gen_html_output(content)




htmlOutput = HuOutput2Html()
htmlOutput.process()

