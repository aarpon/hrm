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
    if { [ catch { exec rm -f $filePath } err ] } {
        ReportError "Problem deleting $filePath: $err"
        # This is not a critical error, do not terminate.
    }
}

proc OpenImage { filename } {

    # A subimage may be indicated between brackets, at the end of the filename
    set exp {^(.*) \((.*)\)$}
    set matched [regexp $exp $filename match path subimage]

    set subOpt ""

    if { $matched } {
        set subOpt "-subImage \"$subimage\""
        set filename $path
    }

    if { [ catch { 
            eval img open "$filename" -logEnable $subOpt
        } imgName ] } {
        ReportError "Problem opening image $filename $subOpt: imgName"
        FinishScript
    }
    $imgName lundo -off
    return $imgName
}

proc SaveImage { image destination filename { type hdf5 } {saveHistory 0} }  {
    global hrm

    MakeOutDir $hrm(outputDir)

    set path [file join $destination $filename]

    if { [ catch { $image save  $path -type $type } savedFile ] } {
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
        set fname "${basename}.Ch$i.h5"
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
        error "Empty channel list to join."
    }

    if { $channels == 1 } {
        $channelList -> $dest
        DeleteImage $channelList
        return
    }

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

proc InitScript {} {
    global huygens

    # Report process ID
    set id [pid]
    Report "\npid=$id"

    # Reduce verbosity, disable undo mechanism to save memory.
    huOpt verb -mode noQs
    huOpt gundo off

    set huygens(globalParam) \
        {dx dy dz dt offX offY offZ offT iFacePrim imagingDir}
    set huygens(channelParam) \
        {micr na ri ril ex em pr ps pcnt exBeamFill objQuality}

    CheckHuygensVersion

    set hrm(deleteFiles) {}

    if { [ catch { DefineScriptParameters } err ] } {
        ReportError "HRM error, problem setting the script parameters: $err"
        FinishScript
    }
}


proc FinishScript {} {
    global hrm

    catch {
        # Can't afford this to fail: make sure it's catched.
        foreach file $hrm(deleteFiles) {
            DeleteFile $file
        }
    }

    # Report finish status.
    exec touch "$hrm(inputDir)/.finished_$hrm(jobID)"
    file attributes "$hrm(inputDir)/.finished_$hrm(jobID)" -permissions 0666
    Report "- DONE --------------------------------\n\n"
    exit
}


proc GetParameter { param {channel 0} } {
    global hrm

    set value "(null)"

    if { [ info exists hrm($param) ] } {
        if { $channel < [llength $hrm($param)] } {
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

    return $value

}

proc MakeOutDir { path } {
    ::FileUtils::checkWriteDest $path
    # If possible, make it readable for everyone, a HRM assumption.
    catch { exec mkdir -m0777 -p $path }
}


proc SetGlobalParameters { imgName } {
    global huygens

    if { [ catch {

        # Common parameters
        foreach param $huygens(globalParam) {
            set value [ GetParameter $param ]
            if {$value != "(null)"} {
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
            if {$value != "(null)"} {
                $imgName setp -chan $setChannel -$param $value
            }
        }
    } err ] } {
        ReportError "Problem setting parameters of $imgName: $err"
        FinishScript
    }
}

proc Deconvolve { image } {
    # Single channel deconvolution

    if { [ catch {

        # TODO

        # Prepare PSF
        set psf [CreateNewImage psf]

        set result [CreateNewImage ${image}_restored]

        $image cmle $psf -> $result -it 1

    } err ] } {
        ReportError "Problem deconvolving $image: $err"
        FinishScript
    }

    return $result

}

proc SaveImagePreview { image destination basename {types thumbnail } } {
    global hrm

    if { $hrm(useThumbnails) == 0 } {
        return
    }

    set dest [join $destination hrm_previews]

    catch {

    foreach type $types {
        switch $type {
            thumbnail {
                ::WebTools::savePreview $img $dest $fn {preview}
            }
            MIP {
                # This also saves a smaller thumbnail
                ::WebTools::savePreview $img $dest $fn {preview 400} 
            }
            SFP {
                if { $hrm(saveSfpPreviews) == 0 } {
                    continue
                }
                ::WebTools::saveTopViewSfp $img $dest ${fn}.sfp
            }
            stack {
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                ::WebTools::saveStackMovie $img $dest ${fn}.stack \
                   $hrm(movieMaxSize)
            }
            tMIP {
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                ::WebTools::saveTimeSeriesMovie $img \
                    $dest ${fn}.tSeries $hrm(movieMaxSize)
            }
            tSFP {
                if { $hrm(saveSfpPreviews) == 0 } {
                    continue
                }
                if { $hrm(movieMaxSize) == 0 } {
                    continue
                }
                ::WebTools::saveTimeSeriesMovie $img \
                    $dest ${fn}.tSeries.sfp - SFP 
            }
        }

    }

    }


}


proc SaveImageComparisons { orig result destination basename } {

    # If not enabled, return
    if { $hrm(maxComparisonSize) == 0 } {
        return
    }

    set dest [join $destination hrm_previews]

    catch {
        ::WebTools::combineStrips [list $orig c] stack $dest ${fn} 300 auto 

        ::WebTools::combineStrips [list $orig c] tSeries $dest ${fn} 300 auto 
    }

}




proc RunDeconvolution {} {
    global hrm

    if { [ catch {

    set imageName [ OpenImage [file join $hrm(inputDir) $hrm(inputFile) ] ]

    set chanCnt [$imageName getdims -mode ch]

    SetGlobalParameters $imageName
    for { set i 0 } { $i < $chanCnt } { incr i } {
        # set all channels parameters
        SetChannelParameters $imageName $i $i
    }

    SaveImagePreview $imageName $hrm(inputDir) $hrm(inputFile) thumbnail

    if { $chanCnt > 1 } {
        # Multichannel images are deconvolved channel by channel.
        set channelFiles [SplitAndSaveChannels $imageName \
            $hrm(inputDir) $hrm(originalFile) ]
            # Delete original to save memory, but we'll have to open it again
            # later.
        DeleteImage $imageName

        set ch 0
        set resultFileList {}
        set result [CreateNewImage hrm_result]
        foreach chf $channelFiles {
            set imageCh [OpenImage $chf]
            # Set this channel parameters again to the split single channel, in
            # case they weren't properly saved in the file. It doesn't cost
            # much, and it's safer.
            SetGlobalParameters $imageCh
            SetChannelParameters $imageCh 0 $ch
            set resCh [Deconvolve $imageCh]
            set saved [SaveImage $resCh $hrm(outputDir) $hrm(outputFile).Ch$ch]
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
        # Single channel images are deconvolved directly
        set result [Deconvolve $imageName]
    }

    SaveImage $result $hrm(outputDir) $hrm(outputFile) $hrm(outputType) 1

    SaveImagePreview $result $hrm(outputDir) $hrm(outputFile)\
        {MIP SFP stack tMIP tSFP}

    if { $chanCnt > 1 } {
        # Open original, again...
        set imageName [ OpenImage [file join $hrm(inputDir) $hrm(inputFile) ] ]
        # Use same parameters as in result.
        $result adopt -> $imageName
        SaveImagePreview $imageName $hrm(outputDir) $hrm(inputFile) {MIP SFP}
    }

    SaveImageComparisons $imageName $result

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
    originalFile "/Users/jose/Sites/hrm_images/jose/huygens_src/objectAnalyzer_test_image_comb_x_1.ics" \
    hrmFileName "/Users/jose/Sites/hrm_images/jose/huygens_src/objectAnalyzer_test_image_comb_x_1.ics" \
    jobID "4b8bbadc0a32e" \
    outputDir "/Users/jose/Sites/hrm_images/jose/huygens_dst" \
    inputDir "/Users/jose/Sites/hrm_images/jose/huygens_src" \
    inputFile "objectAnalyzer_test_image_comb_x_1.ics" \
    outputFile "objectAnalyzer_test_image_comb_x_1Ch0_4b8bbadc0a32e_hrm.ics" \
    outputType "ics" \
    dx 0.05 \
    dy 0.05 \
    dz 0.15 \
    dt 1.0 \
    na {1.4 1.4 1.4 1.4} \
    ri {1.44 1.44 1.44 1.44} \
    ril {1.51 1.51 1.51 1.51} \
    ex {480 495 510 530} \
    em {510 525 535 550} \
    pr {250 250 250 250} \
    ps {2.53 2.53 2.53 2.53} \
    useThumbnails 1 \
    movieMaxSize 300 \
    saveSfpPreviews 1 \
    maxComparisonSize 300 \
    ]

    array set hrm $hrm_list

}


# Start the job.
HRMrun

