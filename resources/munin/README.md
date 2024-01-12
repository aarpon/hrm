# HRM Munin Plugin

This is an absolut minimalistic plugin for the [Munin resource monitoring
tool][1] that will monitor the HRM Job Queue length.

## Prerequisites

As a basic requirement you will need to set up Munin itself. Please refer to
the [Munin documentation][1] or to one of the various distribution-specific
guides found on the web.

In addition, the plugin requires the `mysql` command line interface to be
available on the system.

## Installation

The plugin itself needs to be placed (or symlinked) into Munin's `plugins`
directory. In a default installation this can be done via

```bash
cp -v plugins/hrm_jobqueue /etc/munin/plugins
chmod +x /etc/munin/plugins/hrm_jobqueue
```

The plugin requires three configuration parameters to be set, so it can
connect to the database and check the HRM queue. This is done through a
configuration file located in `/etc/munin/plugin-conf.d/`, an example is
provided in the corresponding directory here and can just be copied there:

```bash
cp -v plugin-conf.d/90-hrm /etc/munin/plugin-conf.d/
```

Make sure to **adjust the values** therein according to your setup!

Using a separate read-only database user (i.e. not the one used for the HRM
itself) that has access to the `job_queue` table is **strongly recommended**!

**FINALLY**, please make sure to re-start the `munin-node` service on the
host, otherwise the new plugin will be ignored.

```bash
service munin-node restart
```

## Results

Graphs of the HRM Queue length will be placed in the `processes` section of
the generated Munin pages.

[1]: http://munin-monitoring.org/