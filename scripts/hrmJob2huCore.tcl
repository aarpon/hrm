# Main deconvolution job script. Based on HuCore templates. Works with pipes.
# HuCore 3.7 or higher required.
proc runDeconvolutionJob { } {
    
    # Print the pid so that HRM can retrieve it for administration purposes.
    set id [pid]
    puts "\npid=$id"

    # Get sessionTime flag for HuCore pipe.
    set sessionID [huOpt execLog -session]

    # Get timeStamp for HuCore pipe.
    set timeID [huOpt wikiKey]

    # Get HuCore tool for template management.
    set huCorePath [huOpt getHuPath]
    set scriptPath "${huCorePath}TclUtils"
    set script "$scriptPath/huTclTaskBackendMain.tcl"

    # Get path and name of the HuCore executable.
    set hucore [getHucoreExecutable $huCorePath]

    # Get environment variables of the deconvolution job.
    set envVariables [getEnvVariables]
    set envList "mode readEnv value [list $envVariables]"

    # Get tasks of the deconvolution job.
    set tasks [getTaskVariables]
    set varList "mode readTask value [list $tasks]"

    # Run the job by creating a pipe with a new HuCore instance.
    if { [catch {
        open "|$hucore -noExecLog \
         -checkUpdates disable -sessionTime $sessionID \
         -batchProcessor 1 -dryRun 0 -taskToken 1 \ 
         -timeStamp $timeID -ppid $id \
         -task $script" r+} varChannel] } {
        reportError "Failed to create pipe: $varChannel\n"
    }

    # Send environment variables and tasks of the decon job to the HuCore pipe.
    puts $varChannel $envList
    flush $varChannel

    puts $varChannel $varList
    flush $varChannel

    # The deconvolution job will be executed when the pipe is read from.
    while {1} {
        set line [gets $varChannel]
        puts $line
        if {[eof $varChannel]} {
            if { [catch {close $varChannel} err] } {
                reportError "\nFailed to close pipe: $err"
            } 
            break
        }
    }
}


proc getHucoreExecutable { huCorePath } {

    if {[Hu_isLinux]} {
        set hucore $huCorePath
        regsub -all {\msvi\M} $hucore bin hucore
        set hucore "${hucore}hucore"
    } else {
        set hucore "${huCorePath}bin/"
        if {[Hu_isMac64bit]} {
            set hucore "${hucore}hucore_64"
        } else {
            set hucore "${hucore}hucore_32"
        }
    }
    return $hucore
}


proc savePreview { image destFile destDir {previews "all"} } {

    if {$previews eq "all"} {
        if { [ catch { 
            ::WebTools::savePreview $image \
                $destDir/hrm_previews $destFile {preview 400} 
        } result ] } {
            reportError "\nFailed to save preview: $result"
        }
    } elseif {$previews eq "xyxz" } {
        if { [ catch { 
            ::WebTools::savePreview $image \
                $destDir/hrm_previews $destFile {preview} 
        } result ] } {
            reportError "\nFailed to save preview: $result"
        }
    } else {
        reportError "\nUnknown set of previews"
    }
}


proc saveStackMovie { image destFile destDir } {

    set movieMaxSize [getMovieMaxSize]
    if {$movieMaxSize > 0} {
        if { [ catch { 
            ::WebTools::saveStackMovie $image \
                $destDir/hrm_previews ${destFile}.stack $movieMaxSize
        } result ] } {
            reportError "\nFailed to save stack movie: $result"
        }
    }
}


proc saveTimeSeriesMovie { image destFile destDir {sfp 0} } {

    set movieMaxSize [getMovieMaxSize]
    if {$movieMaxSize > 0} {
        if {$sfp eq "SFP"} {
            if { [ catch { 
                ::WebTools::saveTimeSeriesMovie $image \
                    $destDir/hrm_previews ${destFile}.tSeries.sfp - SFP 
            } result ] } {
                reportError "\nFailed to save time series movie: $result"
            }
        } else {
            if { [ catch { 
                ::WebTools::saveTimeSeriesMovie $image \
                    $destDir/hrm_previews ${destFile}.tSeries $movieMaxSize
            } result ] } {
                reportError "\nFailed to save time series movie: $result"
            }
        }
    }
}


proc saveTopViewSfp { image destFile destDir } {

    if {[getSaveSfpPreviews]} {
        if { [ catch { 
            ::WebTools::saveTopViewSfp $image \
                $destDir/hrm_previews ${destFile}.sfp
        } result ] } {
            reportError "Failed to save SFP view: $result"
        }
    }
}


proc saveCombinedZStrips { srcImage deconImage destFile destDir } {

    set maxComparisonSize [getMaxComparisonSize]
    if {$maxComparisonSize > 0} {
        if { [ catch { 
            ::WebTools::combineStrips [list $srcImage $deconImage] stack \
                $destDir/hrm_previews ${destFile} $maxComparisonSize auto
        } result ] } {
            reportError "Failed to save Z combined strips: $result"
        }
    }
}


proc saveCombinedTimeStrips { srcImage deconImage destFile destDir } {

    set maxComparisonSize [getMaxComparisonSize]
    if {$maxComparisonSize > 0} {
        if { [ catch { 
            ::WebTools::combineStrips [list $srcImage $deconImage] tSeries \
                $destDir/hrm_previews ${destFile} $maxComparisonSize auto
        } result ] } {
            reportError "Failed to save T combined strips: $result"
        }
    }
}


proc saveAllPreviews { image destFile destDir } {
    savePreview $image $destFile $destDir
    saveStackMovie $image $destFile $destDir
    saveTimeSeriesMovie $image $destFile $destDir
    saveTopViewSfp $image $destFile $destDir
    saveTimeSeriesMovie $image $destFile $destDir "SFP"
}


proc deleteImage { image } {
    if { [ catch {
        $image del
    } result ] } {
        reportError "Failed to delete image: $result"
    }
}


proc openImage { image } {
    if { [ catch {
        set openedImage [img open $image]
    } result ] } {
        reportError "Failed to open image: $result"
        return -1
    } else {
        return $openedImage
    }
}


# Previews: Huygens Core 3.3.1 or higher required.
proc generateImagePreviews { } {

    if {![getUseThumbnails]} {
        return
    }
    set srcImageFullName [getSrcImageFullName]
    set deconImageFullName [getDeconImageFullName]

    # Save deconvolved previews in the deconvolved folder
    set deconImage [openImage $deconImageFullName]
    set destDir [file dirname $deconImageFullName]
    set destFile [file tail $deconImageFullName]
    saveAllPreviews $deconImage $destFile $destDir

    # Save all raw previews in the deconvolved folder.
    set srcImage [openImage $srcImageFullName]
    $deconImage adopt -> $srcImage
    set destFile $destFile.original
    saveAllPreviews $srcImage $destFile $destDir
    
    # Save combined strips for the slicer: Z and time, in the deconvolved folder.
    set destFile [file tail $deconImageFullName]
    saveCombinedZStrips $srcImage $deconImage $destFile $destDir
    saveCombinedTimeStrips $srcImage $deconImage $destFile $destDir

    # Save a few raw previews in the source folder.
    set destDir [file dirname $srcImageFullName]
    set destFile [file tail $srcImageFullName]
    savePreview $srcImage $destFile $destDir "xyxz"
    
    deleteImage $srcImage
    deleteImage $deconImage
}


proc getEnvVariables { } {
    set envList "PHPparser_envList"
    return $envList
}


proc getTaskVariables { } {
    set taskList "PHPparser_taskList"
    return $taskList
}


proc getSrcImageFullName { } {
    set srcImage "PHPparser_srcImage"
    return $srcImage
}


proc getDeconImageFullName { } {
    set deconImage "PHPparser_destImage"
    return $deconImage
}


proc getUseThumbnails { } {
    set useThumbnails "PHPparser_useThumbnails"
    return $useThumbnails
}


proc getMovieMaxSize { } {
    set movieMaxSize "PHPparser_movieMaxSize"
    return $movieMaxSize
}


proc getSaveSfpPreviews { } {
    set saveSfpPreviews "PHPparser_saveSfpPreviews"
    return $saveSfpPreviews
}


proc getMaxComparisonSize { } {
    set maxComparisonSize "PHPparser_maxComparisonSize"
    return $maxComparisonSize
}


proc reportError { errorMsg } {
    printError $errorMsg
    puts $errorMsg
}

# ------------------------------------------------------------------------------


runDeconvolutionJob
generateImagePreviews
exit
