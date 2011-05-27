# This file is part of the Huygens Remote Manager
# Copyright and license notice: see license.txt

# This script contains some procedures that report throught stdout to the HRM
# about image properties. They also handle image previews.

# ---------------------------------------------------------------------------

# Check and retrieve input variables
proc getInputVariables { varList } {
    set error 0
    foreach var $varList {
        set value [Hu_getOpt -$var]
        if { $value == -1 } {
            reportError "Wrong arguments, -$var is missing."
            set error 1
        } else {
            if { [ catch { uplevel set $var \"$value\" } err ] } {
                reportError "ERR $err<br>$var \"$value\"<br>"
            }
            puts "$var $value"
        }
    }

    return $error
}


# Return a key - value pair to the HRM code, to construct an array up there.
proc reportKeyValue {key value} {

    puts "KEY"
    puts "$key"
    puts "VALUE"
    puts "$value"
}


proc reportError {msg} {
    puts "ERROR"
    puts $msg
}


proc reportMsg {msg} {
    puts "REPORT"
    puts $msg
}


proc reportImageDimensions { } {
    set error [ getInputVariables {path filename series} ]
    if { $error } { exit 1 }

    set src [hrmImgOpen $path "$filename" -series $series]

    reportKeyValue "sizeX" [$src getdims -mode x]
    reportKeyValue "sizeY" [$src getdims -mode y]
    reportKeyValue "sizeZ" [$src getdims -mode z]
    reportKeyValue "sizeT" [$src getdims -mode t]
    reportKeyValue "sizeC" [$src getdims -mode ch]
    
    catch { del $src }
} 


# Auxiliary procedure isMultiImgFile.
# Return 1 if the image is of a type that supports sub-images. Currently, only
# LIF.
proc isMultiImgFile { filename } {
    set multiImgExtensions { ".lif" }

    set ext [file extension $filename]
    set isMulti 0
    foreach knownExt $multiImgExtensions {
        if { [string compare -nocase $ext $knownExt] == 0 } {
            set isMulti 1
        }
    }
    return $isMulti
}   


# Script for Huygens Core to explore multi-image files and return their
# subimages. Currently valid for Leica LIF files.
proc reportSubImages {} {

    set imgCount [Hu_getOpt -count]
    set dir [Hu_getOpt -dir]

    if { $imgCount == -1 || $dir == -1 } {
        reportError "Wrong arguments.\
            Use indexed options -img_# to pass the image list to hucore.\
            The number of images must be passed with option -count.\
            The directory that contains them must be passed with -dir."
        exit 1
    }

    for {set i 0} {$i < $imgCount} {incr i} {

        set image [Hu_getOpt -img_$i]

        if { $image == -1 } {
            reportError "Wrong arguments.\
                The number of images must be passed with option -count.\
                The indexed arguments to pass the image list run from 0 to\
                (count - 1)."
            exit 1
        }

        set path "$dir/$image"
        puts "----------------"
        puts "BEGIN IMG"
        puts $image
        puts "PATH" 
        puts "$path"

        if { ![file exists $path] } {
            reportError "Unexisting image '$image'. "
            continue
        }


        if { [isMultiImgFile $path] } {
            puts "TYPE"
            puts "multiple"
            set error [ catch { \
                img preOpen $path } contents]
            if { $error } {
                reportError "Can't find subimages for $image: $contents"
            } else {
                set ver [versionAsInteger]
                if { $ver >= 3030300 } {
                    # Since Huygens 3.3.3, the preOpen command returns an
                    # option-value list
                    catch { array set res $contents }
                    if { ! [info exists res(subImages)] } {
                        set contents {}
                    } else {
                        set contents $res(subImages)
                    }
                }
                puts "COUNT"
                puts "[llength $contents]"
                foreach subImg $contents {
                    puts "SUBIMG"
                    puts "$subImg"
                }
            }
        } else {
            puts "TYPE"
            puts "single"
            puts "COUNT"
            puts "0"
        }
        puts "END IMG"
    }
}


# Opens an image in the HRM repositories, that can include a (subimage) in the
# file name.
proc hrmImgOpen { dir file args } {

    set path [file join $dir $file]

    set exp {^(.*) \((.*)\)$}
    set matched [regexp $exp $path match path subimage]

    puts "REPORT"

    if { $matched } {
        puts "Opening image: '$path' -subImage $subimage"
        set cmd "img open \"$path\" -subImage \"$subimage\" $args"
        if { [ catch {
            set img [eval $cmd]
          } res ] } {
            reportError "\"$path\" ($subimage), $res"
            reportError "command: $cmd"
            set img -1
        }
    } else {
        puts "Opening image: '$path'"
        if { [ catch {
            set img [eval img open \"$path\" $args] } res ]
          } {
            reportError "\"$path\" $res"
            set img -1
        }
    }

    return $img
}


proc generateImagePreview {} {

    if { [info proc ::WebTools::savePreview] == "" } {
        reportError "This tool requires Huygens Core 3.3.1 or higher"
        return
    }

    set error [ getInputVariables {src dest filename sizes scheme series} ]
    if { $error } { exit 1 }

    set emm [ Hu_getOpt -emission ]
    set s [ Hu_getOpt -sampling ]

    set basename $filename
    # Because $filename can actually contain a subdirectory of HRM, we
    # reorganize the strings here:
    set sPath [file join $src $basename ]
    set src [file dirname $sPath]
    set filename [file tail $sPath]

    set dPath [file join $dest $basename ]
    set dest [file dirname $dPath]


    set src [ hrmImgOpen $src $filename -series $series ]
    if { $src == -1 } {
        return
    }

    if { $s != -1 } {
        reportMsg "Setting voxel size $s"
        if { [ catch { eval $src setp -s {$s} } err ] } {
            reportError "Problems setting sampling $s: $err"
            return
        }
    }

    if { $emm != -1 } {
        reportMsg "Setting emission lambdas to $emm"
        if { [ catch { 
            set i 0
            foreach lambda [split [string trim $emm] ] {
                $src setp -chan $i -em $lambda
                incr i
            }
            
        } err ] } {
            reportError "Problems setting wavelengths $emm: $err"
            return
        }
    }
    set channels [$src getdims -mode ch]

    if { [ catch {
        reportMsg "Processing image: generating MIP and scaling."
        ::WebTools::savePreview $src $dest $filename $sizes $scheme
        
        # Make the preview directory writable/readable to all users.
        if { [ file owned $dest ] } {
            if { [ catch { exec chmod 777 $dest } res ] } {
                reportError "$res"
            }
            catch { exec chmod -R a+w $dest }
        }
    }  res ] } {
        reportError "$res"
    } else {
        puts "OK"
    }


}


proc calculateNyquistRate {} {

    set error [ getInputVariables {micr na ex em pcnt ril} ]
    if { $error } { exit 1 }

    a setp -na $na -ex $ex -em $em -pcnt $pcnt -ril $ril \
        -micr $micr -s {1 1 1}

    set nrate [a nyq -tclReturn]

    set sampxy [expr int( 1000 * [lindex $nrate 0] ) ]
    set sampz [expr int( 1000 * [lindex $nrate 2] ) ]

    reportKeyValue "xy" $sampxy
    reportKeyValue "z" $sampz
}


proc versionAsInteger { } {

    set version [huOpt version -engine]
    set exp {([0-9]+)\.([0-9]+)\.([0-9]+)p([0-9]+)}

    set matched [ regexp $exp $version match \
              ceMajor ceMinor1 ceMinor2 cePatch ]

    if { !$matched } {
        reportError "Can't parse version number '$version'"
        exit 1
    } else {
        set verInteger [expr $ceMajor * 1000000  + $ceMinor1 * 10000 \
                                    + $ceMinor2 * 100 + $cePatch]
    }

    return $verInteger

}


proc reportFormatInfo { } {
    set formatInfo [huOpt getFormatInfo]

    puts "KEY"
    puts "formatInfo"
    puts "VALUE"
    puts "$formatInfo"
}


proc reportVersionNumberAsInteger { } {

    set verInteger [ versionAsInteger ]
    
    puts "KEY"
    puts "version"
    puts "VALUE"
    puts "$verInteger"

}


proc getMetaData { } {

    set imgCount [Hu_getOpt -count]
    set dir [Hu_getOpt -dir]

    if { $imgCount == -1 || $dir == -1 } {
        reportError "Wrong arguments.\
            Use indexed options -img_# to pass the image list to hucore.\
            The number of images must be passed with option -count.\
            The directory that contains them must be passed with -dir."
        exit 1
    }

    for {set i 0} {$i < $imgCount} {incr i} {

        set image [Hu_getOpt -img_$i]

        if { $image == -1 } {
            reportError "Wrong arguments.\
                The number of images must be passed with option -count.\
                The indexed arguments to pass the image list run from 0 to\
                (count - 1)."
            exit 1
        }

        set path "$dir/$image"
        puts "----------------"
        puts "BEGIN IMG"
        puts $image
        puts "PATH" 
        puts "$path"

        if { ![file exists $path] } {
            reportError "Unexisting image '$image'. "
            continue
        }

        puts "BEGIN COMMENTS"
        set error [ catch { \
            img preOpen $path } metaData]
        puts "END COMMENTS"

        if { $error } {
            reportError "Can't find metadata for $image: $metaData"
        } else {
            puts "PARAMETERS"
            puts "COUNT"
            puts "[expr [llength $metaData] / 2]"
            foreach {param val} $metaData {
                puts "DATA"
                puts "$param"
                puts "LENGTH"
                puts [llength $val]
                foreach item $val {
                    puts "VALUE"
                    puts $item
                }
            }
        }
        puts "END IMG"
    }
}


proc estimateSnrFromImage {} {

    if { [info proc ::WebTools::estimateSnrFromImage] == "" } {
        reportError "This tool requires Huygens Core 3.5.1 or higher"
        return
    }

    # Mandatory arguments:
    set error [ getInputVariables {
        basename src series dest snrVersion returnImages
    } ]
    if { $error } { exit 1 }

    # Optional arguments:
    set emm [ Hu_getOpt -emission ]
    set s [ Hu_getOpt -sampling ]

    # Opening images
    set srcImg [ hrmImgOpen $src $basename -series $series ]

    if { $s != -1 } {
        reportMsg "Setting voxel size $s"
        if { [ catch { eval $srcImg setp -s {$s} } err ] } {
            reportError "Problems setting sampling $s: $err"
            return
        }
    }

    if { $emm != -1 } {
        reportMsg "Setting emission lambdas to $emm"
        if { [ catch { 
            set i 0
            foreach lambda [split [string trim $emm] ] {
                $srcImg setp -chan $i -em $lambda
                incr i
            }
            
        } err ] } {
            reportError "Problems setting wavelengths $emm: $err"
            return
        }
    }

    # Since HuCore 3.6.1, there is a new SNR estimator available.
    set verHuCo [versionAsInteger]
    if { $verHuCo >= 3060100 && $snrVersion eq "new"} {
	set result [ ::WebTools::computeSnr $srcImg $dest]
    } else {
	set result [ ::WebTools::estimateSnrFromImage $srcImg $dest \
			 "snr_estimation_" jpeg 2 auto \
			 returnImages $returnImages bg auto estimationSize 100 ]
    }

    # Report image name
    reportKeyValue imageName [file join $src $basename]
    reportKeyValue simulationDir $dest

    # Report number of channels
    reportKeyValue channelCnt [expr [llength $result] / 2]

    # Per channel...
    foreach {ch data} $result {
        array set chArr $data

        # Report estimated parameters
        foreach key {estSNR estClipFactor estBG} {
            reportKeyValue ${ch},$key $chArr($key)
        }

        # Report list of generated images
        set simulationList {}
        set simulationImages {}
        set simulationZoom {}
        foreach {snr img} $chArr(imageList) {
            lappend simulationList $snr
            lappend simulationImages "${img}.jpg"
        }
        foreach {snr img} $chArr(zoomList) {
            lappend simulationZoom "${img}.jpg"
        }

        reportKeyValue ${ch},simulationList $simulationList
        reportKeyValue ${ch},simulationImages $simulationImages
        reportKeyValue ${ch},simulationZoom $simulationZoom
    }

}




# ----------------   MAIN routine -----------------
# Execute selected procedure

set tool [Hu_getOpt -tool]
set huTcl [Hu_getOpt -huCoreTcl]

if { $tool == -1 } {
    puts "Wrong arguments.\
        Use option -tool to specify the utility procedure to execute."
    exit 1
}

# Execute procedure
puts "BEGIN PROC"
puts "$tool"
if { [ catch { eval $tool} errMsg ] } {
    reportError "Problems running tool '$tool' in $huTcl: $errMsg, $errorInfo"
}
puts "END PROC"
exit 0
