# -*- coding: utf-8 -*-
"""
GC3lib application classes for HuCore related tasks.

Classes
-------

HuCoreApp()
HuDeconApp()
HuPreviewApp()
HuSNRApp()
    The gc3libs applications.
"""

import os

from . import AbstractApp
from .. import logi, logd, logw, logc, loge


class HuCoreApp(AbstractApp):

    """App object for generic 'hucore' jobs.

    This virtual application calls `hucore` with a given template file and
    retrives the stdout/stderr in a file named `stdout.txt` plus the
    directories `resultdir` and `previews` into a directory `reults_<UID>`
    inside the current directory.
    """

    def __init__(self, job, gc3_output):
        if self.__class__.__name__ == 'HuCoreApp':
            raise TypeError("Not instantiating the virtual class 'HuCoreApp'!")
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        job['infiles'].append(job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = job['template'].split('/')[-1]
        # mandatory application parameters
        appconfig = dict(
            arguments=[job['exec'],
                       '-exitOnDone',
                       '-noExecLog',
                       '-checkForUpdates', 'disable',
                       '-template', templ_on_tgt],
            inputs=job['infiles'],
            outputs=['resultdir', 'previews'],
            # collect the results in a subfolder of GC3Pie's spooldir:
            output_dir=os.path.join(gc3_output, 'results_%s' % job['uid'])
        )
        # extra application parameters
        appconfig.update(
            stderr='stdout.txt',  # combine stdout & stderr
            stdout='stdout.txt'
        )
        super(HuCoreApp, self).__init__(job, appconfig)
        logw('Additional %s parameters: [[template: %s]] [[infiles: %s]]',
             self.__class__.__name__,
             job['template'],
             job['infiles'])

    def terminated(self):
        """This is called when the app has terminated execution."""
        # TODO: #407 process "output_dir" after job has terminated
        #       the results have to be put back to the user's destination
        #       directory (in case of gc3 remote execution, data might have
        #       been collected already)
        # TODO: consider specifying the output dir in the jobfile
        #       for now we use the gc3spooldir as the output_dir, so results
        #       will NOT get moved across different storage locations
        # ==== hucore EXIT CODES ====
        # 0: all went well
        # 130: hucore.bin was terminated with Ctrl-C (interactive console)
        # 143: hucore.bin received the HUP signal (9)
        # 165: the .hgsb file could not be parsed (file missing or with errors)
        # ==== hucore EXIT CODES ====
        super(HuCoreApp, self).terminated()


class HuDeconApp(HuCoreApp):

    """App object for 'hucore' deconvolution jobs."""

    def __init__(self, job, gc3_output):
        super(HuDeconApp, self).__init__(job, gc3_output)


class HuPreviewApp(HuCoreApp):

    """App object for 'hucore' image preview generation jobs."""

    def __init__(self, job, gc3_output):
        super(HuPreviewApp, self).__init__(job, gc3_output)


class HuSNRApp(HuCoreApp):

    """App object for 'hucore' SNR estimation jobs."""

    def __init__(self, job, gc3_output):
        super(HuSNRApp, self).__init__(job, gc3_output)
