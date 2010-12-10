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
    set scriptPath "$huCorePath/TclUtils"
    set script "$scriptPath/huTclTaskBackendMain.tcl"

    # Get environment variables of the deconvolution job.
    set envVariables [getEnvVariables]
    set envList "mode readEnv value [list $envVariables]"

    # Get tasks of the deconvolution job.
    set tasks [getTaskVariables]
    set varList "mode readTask value [list $tasks]"

    # Run the job by creating a pipe with a new HuCore instance.
    if { [catch {
        open "|/usr/local/bin/hucore \
         -checkUpdates disable -sessionTime $sessionID \
         -batchProcessor 1 -dryRun 0 -taskToken 1 \ 
         -timeStamp $timeID -ppid $id \
         -task $script" r+} varChannel] } {
        huOpt report "Failed: $varChannel\n"
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
                huOpt report "\n$err"
            } 
            break
        }
    }
}


proc savePreview { fullImgName image path } {
    if { [ catch { 
        ::WebTools::savePreview $fullImgName \
            $path/hrm_previews $image {preview 400} 
    } result ] } {
        huOpt report $result
    }
}


proc saveStackMovie { fullImgName image path } {
    if { [ catch { 
        ::WebTools::saveStackMovie $fullImgName \
            $path/hrm_previews ${image}.stack 300
    } result ] } {
        huOpt report $result
    }
}


proc saveTimeSeriesMovie { fullImgName image path {sfp 0} } {
    if {$sfp eq "SFP"} {
        if { [ catch { 
            ::WebTools::saveTimeSeriesMovie $fullImgName \
                $path/hrm_previews ${image}.tSeries.sfp - SFP 
        } result ] } {
            huOpt report
        }
    } else {
        if { [ catch { 
            ::WebTools::saveTimeSeriesMovie $fullImgName \
                $path/hrm_previews ${image}.tSeries 300
        } result ] } {
            huOpt report $result
        }
    }
}


proc saveTopViewSfp { fullImgName image path } {
    if { [ catch { 
        ::WebTools::saveTopViewSfp $fullImgName \
            $path/hrm_previews ${image}.sfp
    } result ] } {
        huOpt report $result
    }
}


proc saveAllPreviews { fullImgName image path } {
    savePreview $fullImgName $image $path
    saveStackMovie $fullImgName $image $path
    saveTimeSeriesMovie $fullImgName $image $path
    saveTopViewSfp $fullImgName $image $path
    saveTimeSeriesMovie $fullImgName $image $path "SFP"
}


proc deleteImage { image } {
    if { [ catch {
        $image del
    } result ] } {
        huOpt report $result
    }
}


proc openImage { image } {
    if { [ catch {
        set openedImage [img open $image]
    } result ] } {
        huOpt report $result
        return -1
    } else {
        return $openedImage
    }
}


# Previews: Huygens Core 3.3.1 or higher required.
proc generateImagePreviews { } {
    set srcImageFullName [getSrcImageFullName]
    set destImageFullName [getDestImageFullName]

    set destDir [file dirname $destImageFullName]
    set destFile [file tail $destImageFullName]
    set destImage [openImage $destImageFullName]
    saveAllPreviews $destImage $destFile $destDir
    
    set srcDir [file dirname $srcImageFullName]
    set srcFile [file tail $srcImageFullName]
    set srcImage [openImage $srcImageFullName]
    $destImage adopt -> $srcImage
    saveAllPreviews $srcImage $srcFile $srcDir
    
    deleteImage $srcImage
    deleteImage $destImage
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


proc getDestImageFullName { } {
    set destImage "PHPparser_destImage"
    return $destImage
}


# ------------------------------------------------------------------------------


runDeconvolutionJob
generateImagePreviews
exit
