# TODO


# This file is part of Huygens Remote Manager.

# Huygens Remote Manager is software that has been developed at 
# Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
# Baecker. It allows running image restoration jobs that are processed 
# by 'Huygens professional' from SVI. Users can create and manage parameter 
# settings, apply them to multiple images and start image processing 
# jobs from a web interface. A queue manager component is responsible for 
# the creation and the distribution of the jobs and for informing the user 
# when jobs finished.

# This software is governed by the CeCILL license under French law and 
# abiding by the rules of distribution of free software. You can use, 
# modify and/ or redistribute the software under the terms of the CeCILL 
# license as circulated by CEA, CNRS and INRIA at the following URL 
# "http://www.cecill.info".

# As a counterpart to the access to the source code and  rights to copy, 
# modify and redistribute granted by the license, users are provided only 
# with a limited warranty and the software's author, the holder of the 
# economic rights, and the successive licensors  have only limited 
# liability.

# In this respect, the user's attention is drawn to the risks associated 
# with loading, using, modifying and/or developing or reproducing the 
# software by the user in light of its specific status of free software, 
# that may mean that it is complicated to manipulate, and that also 
# therefore means that it is reserved for developers and experienced 
# professionals having in-depth IT knowledge. Users are therefore encouraged 
# to load and test the software's suitability as regards their requirements 
# in conditions enabling the security of their systems and/or data to be 
# ensured and, more generally, to use and operate it in the same conditions 
# as regards security.

# The fact that you are presently reading this means that you have had 
# knowledge of the CeCILL license and that you accept its terms.



# This script contains the procedures for the processing of the deconvolution
# jobs in Huygens Core.

# All procedures in this file should be made to work in general, for all kind
# of images. Parameters for the images, restoration, and output must be sent
# from the HRM in the form of a list that is dumped in the $hrm array.
#
# To avoid the necessity of sending all the parameters in the command line, by
# now the HRM must write a small procedure DefineScriptParameters that sets
# everything as necessary (see example below).
#
# In the future, when huygens core works as a daemon, all these parameters
# could be passed via a socket.
#
# Written by Jose Vina (jose@svi.nl) in March 2010 for HRM 1.3.



proc ReportError { err } {
    huOpt printError $err
}

proc ReportEndTime { time } {
    global hrm
    global huygens

    # The end time of the deconvolution is not the end time of the job, as
    # normally image previews are also generated. Make it a bit longer.
    set time [expr round ($time + 10 * $huygens(timer,pre)) ]

    set now [Hu_timeStamp]
    set finalDate [clock format $time -format {%Y-%m-%dT%H:%M:%S}]
    Report "EstimatedEndTime: $time\
        (+[expr round( $time - $huygens(timer,startTime) )]) $finalDate"


}

proc MessageFilter { msg } {
    global hrm
    global huygens


    if { [ string first "Classic MLE:" $msg ] > 0 || \
         [ string first "Quick-MLE:" $msg ] > 0 } {
      # New iterarion round, parse the coordinates:
      set chIt [ regexp {channel ([0-9]+)} $msg match ch ]
      set frIt [ regexp {frame ([0-9]+)} $msg match fr ]
      set brIt [ regexp {brick ([0-9]+) of (.+).?\.} $msg match br brRange ]

      if { $chIt } {
          set cCh $ch
      } else {
          set cCh $hrm(currentChannel)
      }
      set chMax $hrm(chanCnt)

      if { $frIt } {
          set cFr $fr
      } else {
          set cFr 0
      }
      set frMax $hrm(frameCnt)

      if { $brIt } {
          set cBr $br
          # Parse the total number of bricks
          if { ! [ regexp  {0,..,([0-9]+)} $brRange match brMax ] } {
              regexp  {0,([0-9]+)} $brRange match brMax ]
          }
          incr brMax
      } else {
          set cBr 0
          set brMax 1
      }

      set expectedRounds [expr $chMax * $frMax  * $brMax ]
      incr huygens(timer,rounds)
      set c $huygens(timer,rounds)
      set huygens(timer,$c,iterations) 0
      set now [Hu_timeStamp]

      if { $cCh != $huygens(timer,cCh) } {
          # starting new channel
          set huygens(timer,cFr) 0
          set huygens(timer,cBr) 0

          set huygens(timer,startTime,$cCh) $now

          if { $cCh > 0 } {
              set timeTilNow [expr $now - $huygens(timer,startTime,0)]
              set tPerChan [expr $timeTilNow / $cCh]

              set totalTime [expr $tPerChan * $hrm(chanCnt) ]
              set finalTime [expr $huygens(timer,startTime,0) + $totalTime]

              # This is probably the best estimation we can do.
              Report "Channel $cCh, time per channel $tPerChan"
              ReportEndTime $finalTime
          }
      }

      if { $cFr != $huygens(timer,cFr) } {
          # starting new frame
          set huygens(timer,cBr) 0

          if { $cCh == 0 && $cFr > 0 } {
              set timeTilNow [expr $now - $huygens(timer,startTime,0)]
              set tPerFrame [expr $timeTilNow / $cFr]

              set tPerChan [expr $tPerFrame * $hrm(frameCnt) ]
              set totalTime [expr $tPerChan * $hrm(chanCnt) ]

              set finalTime [expr $huygens(timer,startTime,0) + $totalTime]
              # Estimate based on the number of frames, for the first channel.
              Report "Frame $cFr, time per frame $tPerFrame"
              ReportEndTime $finalTime
          }
      }

      if { $cBr != $huygens(timer,cBr) } {
          # starting new brick

          if { $cCh == 0 && $cFr == 0 && $cBr > 0 } {
              set timeTilNow [expr $now - $huygens(timer,startTime,0)]
              set tPerBrick [expr $timeTilNow / $cBr]

              set tPerFrame [expr $tPerBrick * $brMax ]
              set tPerChan [expr $tPerFrame * $hrm(frameCnt) ]
              set totalTime [expr $tPerChan * $hrm(chanCnt) ]

              set finalTime [expr $huygens(timer,startTime,0) + $totalTime]
              Report "Brick $cBr, time per brick $tPerBrick"
              ReportEndTime $finalTime
          }
 
      }

      set huygens(timer,cCh) $cCh
      set huygens(timer,cFr) $cFr
      set huygens(timer,cBr) $cBr

      set huygens(timer,brMax) $brMax

      Report "Round $huygens(timer,rounds)/$expectedRounds: \
          channel $cCh/$chMax frame $cFr/$frMax brick $cBr/$brMax"

    }

    if { [ string first "Iteration" $msg ] > -1 } {

        set now [Hu_timeStamp]

        set cCh $huygens(timer,cCh)
        set cFr $huygens(timer,cFr)
        set cBr $huygens(timer,cBr)
        regexp {Iteration ([0-9]+)} $msg match cIt

        set huygens(timer,iteration,$cIt) $now

        if { $cCh == 0 && $cFr == 0 && $cBr == 0 } {
            # Number of iterations per round is unknown (quality criterium may
            # apply first, or in QMLE itMode may be auto), but the input
            # parameter is an estimate.
            set itMax [GetParameter it $cCh]
            if { $hrm(method) == "qmle" && $hrm(itMode) == "auto" } {
                # The QMLE normally converges quickly.
                set itMax 10
            }

            set timeTilNow [expr $now - $huygens(timer,startTime,0)]
            # This estimation is crude and pesimist: the first iteration
            # usually takes longer, as the PSF is calculated then. But that's
            # fine: it will be better later.
            set tPerIteration [expr 0.5 * $timeTilNow / $cIt]
            set tPerBrick [expr $tPerIteration * $itMax ]

            if { $cIt > 1 } {
                # A better estimate comes after the second iteration.
                set timeTilNow [expr $now - $huygens(timer,iteration,1)]
                set tPerIteration [expr $timeTilNow / ($cIt - 1)]
                set timeFirstIt \
                [expr $huygens(timer,iteration,1) - $huygens(timer,startTime,0)]
                set tPerBrick [expr $tPerIteration*($itMax - 1) + $timeFirstIt ]
            }

            if { $cIt < 2 || [expr $cIt % 5] == 0 } {
                # Report estimate for the first iterations, and then every 5.
                set tPerFrame [expr $tPerBrick * $huygens(timer,brMax) ]
                set tPerChan [expr $tPerFrame * $hrm(frameCnt) ]
                set totalTime [expr $tPerChan * $hrm(chanCnt) ]

                set finalTime [expr $huygens(timer,startTime,0) + $totalTime]
                Report "Iteration $cIt, time per it $tPerIteration"
                ReportEndTime $finalTime
            }
          }
    }

    return "normal"

}

proc Hu_Print { {msg ""} } {
    # Redefine Hu_Print so that it also reports a timestamp when necessary:
    if { [catch { MessageFilter $msg } type ] } {
        ReportError "Problem with msg: $type"
        return
    }
    switch $type {
        timestamp {
            puts "(TS [Hu_timeStamp]) $msg"
        }
        normal {
            puts $msg
        }
        hide {
        }
    }
}

proc Report { msg } {
    puts $msg
}


proc CreateNewImage { basename {x 32} {y 32} {z 32} {t 0} {ch 1} } {
    set newName ${basename}
    set i 0
    while { [img exists $newName] } {
        incr i
        set newName ${basename}_$i
    }
    if {  [ catch  { img create $newName -logEnable \
       -dim [list $x $y $z $t] -chan $ch  } imgName ] } {
           ReportError "Problem creating image $basename: $imgName"
           FinishScript
       }
    return $imgName
}

proc DeleteImage { imageName } {
    if { [ catch { $imageName del } err ] } {
        ReportError "Problem destroying $imageName: $err"
        # This is not a critical error, do not terminate.
    }
}

proc DeleteFile { filePath } {
    if { [ catch { file delete $filePath } err ] } {
        ReportError "Problem deleting $filePath: $err"
        # This is not a critical error, do not terminate.
    }
}

proc OpenImage { filename {options ""} } {

    # A subimage may be indicated between brackets, at the end of the filename
    set exp {^(.*) \((.*)\)$}
    set matched [regexp $exp $filename match path subimage]

    set subOpt ""

    if { $matched } {
        set subOpt "-subImage \"$subimage\""
        set filename $path
    }

    if { [ catch { 
            eval img open "$filename" -logEnable $subOpt $options
        } imgName ] } {
        ReportError "Problem opening image $filename $subOpt: imgName"
        FinishScript
    }
    $imgName lundo -off
    return $imgName
}

proc ConfigureOriginalImage { img } {
    global hrm

    if { $hrm(isTimeSeries) == 1 && $hrm(isThreeDimensional) == 0 } {
        if { [ catch {
            $img convertZ2T
        } err ] } {
            ReportError "Problem converting stack to time series: $err"
            FinishScript
        }
    }

    set hrm(chanCnt) [$img getdims -mode ch]
    set hrm(frameCnt) [$img getdims -mode t]

}

proc ConfigureResultImage { img } {
    global hrm

    if { $hrm(outputType) == "ims" } {
        # An old HRM hack to store 2D time series as Z stacks in Imaris files.
        set convert 0
        if { $hrm(isTimeSeries) == 1 && $hrm(isThreeDimensional) == 0 } {
            # 2D time series
            set convert 1
        }
        if { $hrm(isTimeSeries) == 0 && $hrm(isThreeDimensional) == 1 } {
            # 3D stack, this shouldn't need a conversion
            set convert 1
        }

        if { $convert } {
            if { [ catch {
                $img convertT2Z
            } err ] } {
                ReportError "Problem converting time series to stack: $err"
            }
        }
    } else {
        # All other file formats, not Imaris.
        set convert 0
        if { $hrm(isTimeSeries) == 0 && $hrm(isThreeDimensional) == 0 } {
            # Single slice, save as 2D image. It isn't clear why this is
            # necessary.
            set convert 1
        }
        if { $convert } {
            if { [ catch {
                $img convert3d22d
            } err ] } {
                ReportError "Problem converting single slice to 2D image: $err"
            }
        }
    }


    $img comment "# Image restored via Huygens Remote Manager"
    $img comment "# Job ID: $hrm(jobID)"


}

proc SaveImage { image destination filename { type hdf5 } {saveHistory 0} }  {
    global hrm

    MakeOutDir $hrm(outputDir)


    set path [file join $destination $filename]
    set options "-type $type"

    if { [string first "tiff" $type] == 0 } {
        append options " -tiffMultiDir -cmode scale"
    }

    if { [ catch { eval $image save "$path" $options } savedFile ] } {
        ReportError "Problem saving image $image to $destination: $savedFile"
        FinishScript
    }

    if { $saveHistory } {
       if { [ catch { $image history -details -format txt \
           -save "$path.history.txt" } err ] } {
        ReportError "Problem saving image history to $destination: $err"
       }
    }
    return $savedFile

}


proc SplitAndSaveChannels { image directory basename } {
    global hrm

    if { [ catch { $image split -mode all } channels ] } {
        ReportError "Problem splitting image $image: $channels"
        FinishScript
    }
    set files {}
    set i 0
    foreach chImg $channels {
        set fname "${basename}.Ch$i"
        set saved [SaveImage $chImg $directory $fname]
        lappend files $saved
        lappend hrm(deleteFiles) $saved
        DeleteImage $chImg
        incr i
    }

    return $files
}

proc JoinChannels { channelList dest } {
    set channels [llength $channelList]

    if { $channels == 0 } {
        ReportError "Empty channel list to join."
        FinishScript
    }

    if { $channels == 1 } {
        # This case shouldn't happen in HRM.
        $channelList -> $dest
        DeleteImage $channelList
        return
    }

    if { [ catch {
        set chan0 [lindex $channelList 0]
        set chan1 [lindex $channelList 1]
            $chan0 join $chan1 -> $dest
            DeleteImage $chan0
            DeleteImage $chan1
            for { set i 2 } { $i < $channels } { incr i } {
                set chan [lindex $channelList $i]
                $dest join $chan -> $dest
            DeleteImage $chan
            }
    } err ] } {
        ReportError "Problem joining channels: $err"
        FinishScript
    }
}


proc CheckHuygensVersion {} {

    set version 0

    # Get the current version reported as an integer number.
    if { ! [ catch { ::Utils::intHuygensVersion } intVer ] } {
        set version $intVer
    }

    if { $version < 3050000 } {
        ReportError "This version of HRM requires Huygens Core 3.5 or higher.\
            You are using version [huOpt report]"
        FinishScript
    }

}

proc ReviewScriptParameters {} {
    global hrm

    set type $hrm(outputType)
    set filename $hrm(outputFile)

    if { [string first "tiff" $type] == 0 } {
        # When saving as TIFF, make sure the name doesn't end in a number.
        # This is currently done in the HRM code, so it shouln't happen, but in
        # the future it may make some sense to do it here.
        set last [string range [file rootname $filename] end end]
        if { [ string is integer $last ] } {
            set root [file rootname $filename]
            set ext [file extension $filename]
            set filename "${root}_hrm.$ext"
            set hrm(outputFile) $filename
        }
    }

}

proc InitScript {} {
    global huygens

    # Report process ID
    set id [pid]
    Report "\npid=$id"

    set huygens(timer,startTime) [Hu_timeStamp]
    set huygens(timer,count) -1
    set huygens(timer,rounds) 0

    set huygens(timer,cCh) -1
    set huygens(timer,cFr) -1
    set huygens(timer,cBr) -1

    # Reduce verbosity, disable undo mechanism to save memory.
    huOpt verb -mode noQs
    huOpt gundo off

    # Parameter names:
    set huygens(globalParam) \
        {dx dy dz dt offX offY offZ offT iFacePrim imagingDir}

    set huygens(channelParam) \
        {micr na ri ril ex em pr ps pcnt exBeamFill objQuality}

    # A list of temporary files to be deleted at the end of the script:
    set hrm(deleteFiles) {}
    set hrm(outputFiles) {}

    CheckHuygensVersion

    SetDefaultDeconvolutionParameters

    if { [ catch { DefineScriptParameters } err ] } {
        ReportError "HRM error, problem setting the script parameters: $err"
        FinishScript
    }

    if { [ catch { ReviewScriptParameters } err ] } {
        ReportError "HRM error, problem reviewing the script parameters: $err"
        FinishScript
    }
}

proc HandleOutputFiles {} {
    global hrm

    foreach file $hrm(deleteFiles) {
        DeleteFile $file
    }


    if { $hrm(imagesOwnedBy) != "-" } {
        foreach file $hrm(outputFiles) {
            if { [ catch { 
                exec chown $hrm(imagesOwnedBy) "$file"
                exec chgrp $hrm(imagesGroup) "$file"
            } err ] } {
                ReportError "Problem changing ownership of $file: $err"
            }

            # See if an .ids file exists.
            if { [file extension $file] == ".ics" } {
                set ids "[file rootname $file].ids"
                if { [file exists $ids] } {
                    if { [ catch { 
                       exec chown $hrm(imagesOwnedBy) "$ids"
                       exec chgrp $hrm(imagesGroup) "$ids"
                    } err ] } {
                        ReportError "Problem changing ownership of $ids: $err"
                    }
                }
            }
        }
    }

}


proc FinishScript {} {
    global hrm
    global huygens

    catch {
        # Can't afford this to fail: make sure it's catched.
        HandleOutputFiles
    }

    # Report finish status.
    exec touch "$hrm(inputDir)/.finished_$hrm(jobID)"
    file attributes "$hrm(inputDir)/.finished_$hrm(jobID)" -permissions 0666

    set now [Hu_timeStamp]
    Report "Total job time:\
        [expr round($now - $huygens(timer,startTime)) ] s"
    Report "- DONE --------------------------------\n\n"
    exit
}

proc SetDefaultDeconvolutionParameters {} {
    global defaults

    set hrm_defaults [ list \
        method cmle \
        psf theoretical-variant \
        psfFile - \
        bgMode auto \
        bg 0 \
        blMode auto \
        q 0.1 \
        it 40 \
        itMode auto \
        sn auto \
        brMode auto \
    ]

    array set defaults $hrm_defaults
}


proc GetParameter { param {channel 0} {notNull 0} } {
    global hrm
    global defaults

    set value "(null)"

    if { [ info exists hrm($param) ] } {
        if { $channel == "all"} {
            # return in vector mode
            set value $hrm($param)
        } elseif { $channel < [llength $hrm($param)] } {
            set value [lindex $hrm($param) $channel]
        } else {
            # Reuse last value if more channels are necessary.
            set value [lindex $hrm($param) end]
            ReportError "Parameter $param not defined for channel $channel,\
                using '$value'"
        }
    } else {
        ReportError "Parameter $param not defined"
    }

    if { $value == "(null)" && [ info exists defaults($param) ] } {
        if { $channel == "all"} {
            set value $defaults($param)
        } elseif { $channel < [llength $defaults($param)] } {
            set value [lindex $defaults($param) $channel]
        } else {
            # Reuse last value if more channels are necessary.
            set value [lindex $defaults($param) end]
        }
        ReportError "Using default value $value"
    }

    if { $notNull && $value == "(null)" } {
        ReportError "$param is required!"
        FinishScript
    }

    return $value

}

proc MakeOutDir { path } {
    global hrm

    if { ! [::FileUtils::checkWriteDest \
        [file join $path hrm_test_write_file.txt] ] } {
            ReportError "Can't write to directory $path"
            FinishScript
    }

    # If possible, make it accessible to everyone, a HRM assumption.
    if { [ catch {
        exec chmod a+w "$path"
        exec chmod a+r "$path"
        exec chmod a+x "$path"
        } err ] } {
        ReportError "Can't change permissions of $path: $err"
    }

    if { $hrm(imagesOwnedBy) != "-" } {
       if { [ catch {
           exec chown -f $hrm(imagesOwnedBy) "$path"
           exec chgrp -f $hrm(imagesGroup) "$path"
           } err ] } {
               ReportError "Can't change ownership of $path: $err"
           }
    }
}


proc SetGlobalParameters { imgName } {
    global huygens

    if { [ catch {

        # Common parameters
        foreach param $huygens(globalParam) {
            set value [ GetParameter $param ]
            if {$value != "(null)" && $value != "-" } {
                $imgName setp -$param $value
            }
        }

    } err ] } {
        ReportError "Problem setting global parameters of $imgName: $err"
        FinishScript
    }
}



proc SetChannelParameters { imgName setChannel withElement  } {
    global huygens
    # HRM handles single channel images, but parameters may refer to a channel >
    # 0 in the original image.

    if { [ catch {

        # Per channel parameters:
        foreach param $huygens(channelParam) {
            set value [ GetParameter $param $withElement ]
            if {$value != "(null)" && $value != "-" } {
                $imgName setp -chan $setChannel -$param $value
            }
        }
    } err ] } {
        ReportError "Problem setting parameters of $imgName: $err"
        FinishScript
    }
}

proc ProcessSNR { sn img currentCh method } {
    global hrm

    set maxCh [$img getdims -mode ch]
    if { $method == "qmle" } {
        # QMLE accepts only one SNR value, even with multichannel deconvolution.
        set maxCh 1
    }
    set sn [lrange $sn 0 [expr $maxCh - 1]]

    if { [ catch {
        if { "auto" in $sn } {
            Report "Automatic estimation of the signal-to-noise ratio:"
            array set snr [::ImProcess::estimateSnr $img bg auto]

            # default value, in case something goes wrong:
            set val 20

            foreach ch [lsearch -all $sn "auto"] {
                set result $snr(Ch_$ch)
                array set data $result
                set val $data(SNR)
                # Replace 'auto' by the estimated value
                set sn [lreplace $sn $ch $ch $val]
                if { $hrm(channelProcessing) == "split" } {
                    Report "Using estimated SNR for channel\
                        $currentCh: $result"
                } else {
                    Report "Using estimated SNR for channel $ch: $result"
                }
            }
        }
    } err ] } {
        ReportError "Problems estimating the SNR: $err"
    }

    return $sn
}

proc PreparePsf { img psf psfFile } {

    if { $psf == "measured" } {
        # Open an experimental PSF stored in a file.

        # When processing all channels together, the PSF may be given as
        # separate files per channel, or as a multichannel image.
        set fCnt [llength $psfFile]

        if { $fCnt == 1 } {
            set psfImg [ OpenImage $psfFile ]
        } else {
            set toJoin {}
            set psfImg [ CreateNewImage combinedExpPsf ]
            foreach f $psfFile {
                lappend toJoin [ OpenImage $f ]
            }
            JoinChannels $toJoin $psfImg
        }
    } else {
        set psfImg [ CreateNewImage theoPsf ]

        if { $psf == "theoretical-fixed" } {
            # Generate a fixed theoretical PSF here.
            $img genpsf -> psfImg -dims padpar
        }
        # If PSF is variant, it's left empty here and calculated automatically
        # during the restoration, accordingly to the brick mode.
    }

    return $psfImg

}


proc GenerateDeconCommand { img ch } {
    global hrm

    # Result image
    set resultName [string range $img 0 99]
    set result [CreateNewImage ${resultName}_restored]

    if { $ch != "all" && $hrm(chanCnt) > 1 } {
        $result comment "# Intermediate image for channel $ch"
    }

    # cmle or qmle
    set method [ GetParameter method $ch 1 ]

    # 'theoretical-fixed' 'theoretical-variant' or 'experimental'
    set psf [ GetParameter psf $ch ]
    set psfFile [ GetParameter psfFile $ch 1 ]

    # auto | manual | lowest | object
    set bgMode [ GetParameter bgMode $ch 1 ]
    set bg [ GetParameter bg $ch ]

    # bleaching off | auto
    set blMode [ GetParameter blMode $ch 1 ]

    # Quality change
    set q [ GetParameter q $ch 1 ]

    # Max number of iterations
    set it [ GetParameter it $ch 1 ]

    # QMLE only: auto | manual
    set itMode [ GetParameter itMode $ch 1 ]

    # Signal to noise ratio
    set sn [ GetParameter sn $ch 1 ]
    set sn [ ProcessSNR $sn $img $ch $method ]

    # Brick mode: auto | one | few | normal | more | sliceBySlice
    set brMode [ GetParameter brMode $ch 1 ]

    set psfImg [ PreparePsf $img $psf $psfFile ]

    set cmd "$img $method $psfImg -> $result -bgMode $bgMode -bg {$bg} \
        -blMode {$blMode} -it $it -sn {$sn} -brMode $brMode"

    if { $method == "qmle" } {
        append cmd " -itMode $itMode"
    } else {
        append cmd " -q $q"
    }

    return [list method $method cmd $cmd psf $psfImg result $result]

}


proc Deconvolve { image {ch 0} } {
    # Single channel deconvolution

    if { [ catch {

        # TODO

        array set dec [ GenerateDeconCommand $image $ch ]

        Report "Running deconvolution: $dec(cmd)"

        eval $dec(cmd)


    } err ] } {
        ReportError "Problem deconvolving $image: $err"
        FinishScript
    }

    return $dec(result)

}

proc SaveImagePreview { img destination basename {types preview } } {
    global hrm

    if { $hrm(useThumbnails) == 0 } {
        return
    }

    Report "Saving image previews $types"
    # HRM thumbnails and previews are saved in a subdirectory hrm_previews.
    set dest [file join $destination hrm_previews]
    MakeOutDir $dest
    set fn $basename

    if { [ catch {

    foreach type $types {
        switch $type {
            thumbnail {
                # Tiny image
                set o [::WebTools::savePreview $img $dest $fn {thumbnail}]
            }
            preview {
                # Small 3D projection MIP
                set o [::WebTools::savePreview $img $dest $fn {preview}]
            }
            MIP {
                # Large 3D projection MIP. This also saves a preview.
                set o [::WebTools::savePreview $img $dest $fn {preview 400} ]
            }
            SFP {
                # 3D rendering: top view SFP.
                if { $hrm(saveSfpPreviews) == 0 } {
                    continue
                }
                set o [::WebTools::saveTopViewSfp $img $dest ${fn}.sfp]
            }
            stack {
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                set o [::WebTools::saveStackMovie $img $dest ${fn}.stack \
                   $hrm(movieMaxSize)]
            }
            tMIP {
                # Time series top view MIP.
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                set o [::WebTools::saveTimeSeriesMovie $img \
                    $dest ${fn}.tSeries $hrm(movieMaxSize)]
            }
            tSFP {
                # Time series 3D rendering.
                if { $hrm(saveSfpPreviews) == 0 } {
                    continue
                }
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                set o [::WebTools::saveTimeSeriesMovie $img \
                    $dest ${fn}.tSeries.sfp - SFP ]
            }
        }

    }
    if { $o != "" } { 
        foreach f $o {
            lappend hrm(outputFiles) $f
        }
    }

    } err ] } {
        ReportError "Problem generating previews: $err"
    }


}


proc SaveImageComparisons { orig result destination basename } {
    global hrm

    # If not enabled, return
    if { $hrm(maxComparisonSize) == 0 } {
        return
    }

    # HRM thumbnails and previews are saved in a subdirectory hrm_previews.
    Report "Saving image comparisons"

    set dest [file join $destination hrm_previews]
    MakeOutDir $dest
    set fn $basename

    if { [ catch {
        set o [::WebTools::combineStrips [list $orig $result] stack \
            $dest ${fn} 300 auto ]
        if { $o != "" } { lappend hrm(outputFiles) $o }

        set o [::WebTools::combineStrips [list $orig $result] tSeries \
            $dest ${fn} 300 auto ]
        if { $o != "" } { lappend hrm(outputFiles) $o }
    } err ] } {
        ReportError "Problem generating comparisons: $err"
    }

}




proc RunDeconvolution {} {
    global hrm
    global huygens

    if { [ catch {

    set opt ""
    if { $hrm(isTimeSeries) != 1 } {
        set opt " -series off"
    }

    set imageName [ OpenImage [file join $hrm(inputDir) $hrm(inputFile) ] $opt ]

    ConfigureOriginalImage $imageName

    if { $hrm(parametersFrom) == "template" } {
        # Some file formats contain validated metadata that doesn't need to be
        # overwritten, if the user is certain about it.
        SetGlobalParameters $imageName
        for { set i 0 } { $i < $hrm(chanCnt) } { incr i } {
            # set all channels parameters
            SetChannelParameters $imageName $i $i
        }
    }

    SaveImagePreview $imageName $hrm(inputDir) $hrm(inputFile) preview

    if { $hrm(chanCnt) > 1 && $hrm(channelProcessing) == "split" } {
        # Multichannel images are deconvolved channel by channel.
        set channelFiles [SplitAndSaveChannels $imageName \
            $hrm(inputDir) $hrm(inputFile) ]
            # Delete original to save memory, but we'll have to open it again
            # later.
        DeleteImage $imageName

        # Preprocessing time:
        set huygens(timer,pre) \
            [ expr [Hu_timeStamp] - $huygens(timer,startTime) ]
        Report "Preprocessing time: [expr round( $huygens(timer,pre)) ] s"

        set ch 0
        set resultFileList {}
        set result [CreateNewImage hrm_result]
        foreach chf $channelFiles {
            Report "Processing channel $ch"
            set imageCh [OpenImage $chf]
            set resCh [Deconvolve $imageCh $ch]
            set saved [SaveImage $resCh $hrm(outputDir) $hrm(outputFile)_Ch$ch]
            lappend hrm(deleteFiles) $saved
            lappend resultFileList $saved
            DeleteImage $imageCh
            DeleteImage $resCh
            incr ch
        }
        foreach rFile $resultFileList {
            lappend resultList [ OpenImage $rFile ]
        }
        JoinChannels $resultList $result
    } else {

        # Preprocessing time:
        set huygens(timer,pre) \
            [ expr [Hu_timeStamp] - $huygens(timer,startTime) ]
        Report "Preprocessing time: [expr round( $huygens(timer,pre)) ] s"

        # Single channel images are deconvolved directly
        if { $hrm(chanCnt) == 1 } {
            Report "Processing single channel image"
            set result [Deconvolve $imageName 0]
        } else {
            Report "Processing multi-channel image in a go"
            set result [Deconvolve $imageName "all"]
        }
    }

    set now [Hu_timeStamp]
    Report "Total deconvolution time:\
        [expr round($now - $huygens(timer,startTime)) ] s"

    ConfigureResultImage $result
    lappend hrm(outputFiles) \
       [ SaveImage $result $hrm(outputDir) $hrm(outputFile) $hrm(outputType) 1 ]

    if { $hrm(chanCnt) > 1 && $hrm(channelProcessing) == "split" } {
        # Open original, again...
        set imageName [ OpenImage [file join $hrm(inputDir) $hrm(inputFile) ] ]
        # Use same parameters as in result.
        if { [ catch { $result adopt -> $imageName } err ] } {
            ReportError "Problem setting the original's parameters: $err"
        }
    }

    SaveImagePreview $result $hrm(outputDir) $hrm(outputFile)\
        {MIP SFP stack tMIP tSFP}
    SaveImagePreview $imageName $hrm(outputDir) "$hrm(outputFile).original" \
        {MIP SFP}
    SaveImageComparisons $imageName $result $hrm(outputDir) $hrm(outputFile)

    DeleteImage $imageName
    DeleteImage $result

    } err ] } {
        # End catch
        ReportError "Problem running HRM deconvolution: $err"
    }
}

proc HRMrun {} {
    InitScript
    RunDeconvolution
    FinishScript
}






# THIS PART CHANGES FOR EACH JOB ---------------------
# These parameters must be set by JobDescription.php, overwriting this sample
# procedure with the correct one.

proc DefineScriptParameters {} {

    global hrm

    set hrm_list [list \
    jobID "4b8bbadc0a32e" \
    \
    inputDir "/Users/jose/Sites/hrm_images/jose/huygens_src" \
    inputFile "objectAnalyzer_test_image_comb_x_1.ics" \
    outputDir "/Users/jose/Sites/hrm_images/jose/huygens_dst" \
    outputFile "objectAnalyzer_test_image_comb_x_1Ch0_4b8bbadc0a32e_hrm.h5" \
    \
    inputDir "/Users/jose/Desktop/HRM" \
    inputFile "HRM_test_2_chan_2_frame.h5" \
    inputDir "/Users/jose/Sites/hrm_images/jose/huygens_src" \
    inputFile "objectAnalyzer_test_image_comb_x_1.ics" \
    outputDir "/Users/jose/Desktop/HRM/results" \
    outputFile "HRM_test_1_chan_1_frame_restored.h5" \
    \
    outputType "hdf5" \
    \
    parametersFrom "template" \
    channelProcessing "all" \
    isTimeSeries 0 \
    isThreeDimensional 1 \
    \
    micr {"confocal"} \
    dx 0.05 \
    dy 0.05 \
    dz 0.15 \
    dt 1.0 \
    na {1.4 1.4 1.4 1.4} \
    ril {1.51 1.51 1.51 1.51} \
    ri {1.44 1.44 1.44 1.44} \
    ex {480 495 510 530} \
    em {510 525 535 550} \
    pr {250 250 250 250} \
    ps {2.53 2.53 2.53 2.53} \
    offX 0 \
    offY 0 \
    offZ 0 \
    offT 0 \
    iFacePrim 0 \
    imagingDir upward \
    pcnt 1 \
    exBeamFill 2.0 \
    objQuality perfect \
    \
    useThumbnails 1 \
    movieMaxSize 300 \
    saveSfpPreviews 1 \
    maxComparisonSize 300 \
    imagesOwnedBy "jose" \
    imagesGroup "staff" \
    \
    method cmle \
    psf theoretical-variant \
    psfFile - \
    bgMode auto \
    bg 0 \
    blMode auto \
    q 0.1 \
    it 20 \
    itMode auto \
    sn auto \
    brMode auto \
    ]

    array set hrm $hrm_list

}


# Start the job.
HRMrun

