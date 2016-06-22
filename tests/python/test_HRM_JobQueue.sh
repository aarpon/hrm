#!/bin/bash

export PYTHONPATH=$PYTHONPATH:../../lib/python/
python test_HRM_JobQueue.py > test_HRM_JobQueue.out

clear ; cat test_HRM_JobQueue.out

