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


    def insert_cell(self,content, style, colspan=0):
        if colspan == 0:
            colspan = 1

        cell = '<td class=\"%s\" colspan=\"%s\">' % (style, colspan)
        cell += '%s' % content
        cell += '</td>'

        return cell


    def insert_row(self,content):
        row = '<tr>'
        row += '%s' % content
        row += '</tr>'

        return row


    def insert_table(self,content):
        table = '<table>'
        table += '%s' % content
        table += '</table>'

        return table


    def insert_div(self,content, id=-1):
        if id == -1:
            div = '<div>'
            div += '%s' % content
            div += '</div>'
        else:
            div = '<div id=\"%s\">' % id
            div += '%s' % content
            div += '</div>'
            div += '<!-- %s -->' % id

        return div


    def write_micr_warning(self,job_output):
        pattern = '(.*){Microscope conflict for channel ([0 - 9]):(. *)'
        matchObj = re.match(pattern, job_output, re.S)

        if matchObj:
            warning = '<p><b><u>WARNING</u>:</b>'
            warning += ' The <b>microscope type</b> selected to deconvolve '
            warning += 'this image <b>may not<br />be correct</b>. '
            warning += 'The acquisition system registered a different '
            warning += 'microscope type<br />in the image metadata. '
            warning += 'The restoration process may produce '
            warning += '<b>wrong results</b><br />if the microscope type '
            warning += 'is not set properly.<br />'

            row = self.insert_cell(warning, "text")
            table = self.insert_row(row)
            div = self.insert_table(table)
            html = self.insert_div(div, "warning")


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
            print "Found colocalization results: "


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
            self.path = "/home/daniel/Desktop/scheduler_log_warning.txt"

        with open(self.path, 'r') as content_file:
            content = content_file.read()

        self.gen_html_output(content)




htmlOutput = HuOutput2Html()
htmlOutput.process()

