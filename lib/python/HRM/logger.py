#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Logging helper module.
"""

import logging
import gc3libs

__all__ = ["logw", "logi", "logd", "loge", "logc"]

# we set a default loglevel and add some shortcuts for logging:
LOGLEVEL = logging.WARN
gc3libs.configure_logger(LOGLEVEL, "qmgc3")

logw = gc3libs.log.warn
logi = gc3libs.log.info
logd = gc3libs.log.debug
loge = gc3libs.log.error
logc = gc3libs.log.critical
