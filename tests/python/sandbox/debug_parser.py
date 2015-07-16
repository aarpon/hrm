import sys
import time

import gc3libs

import pyinotify
import pprint

import HRM

import logging

loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug

fname = '/var/www/hrm/run/spool/new/deconvolution_job.cfg'

job = HRM.JobDescription(fname, 'file')
