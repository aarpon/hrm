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

proc reportHuCoreLicense { } {
    reportKeyValue "license" [huOpt license]
}


proc reportImageDimensions { } {
    set error [ getInputVariables {path filename series} ]
    if { $error } { exit 1 }

    if { [ catch {
        set src [hrmImgOpen $path "$filename" -series $series]

        set sizeX [$src getdims -mode x]
        set sizeY [$src getdims -mode y]
        set sizeZ [$src getdims -mode z]
        set sizeT [$src getdims -mode t]
        set sizeC [$src getdims -mode ch]
    } result ] } {
        set sizeX 0
        set sizeY 0
        set sizeZ 0
        set sizeC 0
        set sizeT 0
    }

    reportKeyValue "sizeX" $sizeX
    reportKeyValue "sizeY" $sizeY
    reportKeyValue "sizeZ" $sizeZ
    reportKeyValue "sizeT" $sizeT
    reportKeyValue "sizeC" $sizeC

    catch { del $src }
}


# Auxiliary procedure isMultiImgFile.
# Return 1 if the image is of a type that supports sub-images. Currently, only
# LIF, LOF and CZI.
proc isMultiImgFile { filename } {
    set multiImgExtensions { ".lif" ".lof" ".czi" ".nd"}

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
# subimages. Currently valid for Leica LIF, LOF and Zeiss CZI files.
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

        if { ![isMultiImgFile $path] } {
            puts "TYPE"
            puts "single"
            puts "COUNT"
            puts "0"
            puts "END IMG"
            continue
        }

        puts "TYPE"
        puts "multiple"

        if { [ catch {
            img preOpen $path 
        } contents ] } {
            reportError "Can't find subimages for $image: $contents"
            puts "END IMG"
            continue
        }

        # LIFs can report 2-level nesting sub images, CZIs can't.
        # Parse CZIs and 1st nesting level LIFs accordingly.
        set extension [file extension $path]
        if { [string equal -nocase $extension ".czi"] } {
            set subImages [lindex $contents 1]
        } elseif { [string equal -nocase $extension ".nd"] } {
            set subImages [lindex $contents 1]
        } elseif { [string equal -nocase $extension ".lif"]
               || [string equal -nocase $extension ".lof"]} {
            set resDict [dict create {*}[lindex $contents 1]]
            set subImages [dict keys $resDict]

            # Leave no debries to the next iteration.
            dict unset $resDict $subImages
        }

        puts "COUNT"
        puts "[llength $subImages]"
        foreach subImg $subImages {
            puts "SUBIMG"
            puts "$subImg"
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

    if { $matched } {
        # puts "Opening image: '$path' -subImage $subimage"
        set cmd "img open \"$path\" -subImage \"$subimage\" $args"
        if { [ catch {
            set img [eval $cmd]
          } res ] } {
            reportError "\"$path\" ($subimage), $res"
            reportError "command: $cmd"
            set img -1
        }
    } else {
        # puts "Opening image: '$path'"
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

    }  res ] } {
        reportError "$res"
    } else {
        puts "OK"
    }


}


proc calculateNyquistRate {} {

    set error [ getInputVariables {micr na ex em pcnt ril} ]
    if { $error } { exit 1 }
    
    set img [img create temp]

    $img setp -na $na -ex $ex -em $em -pcnt $pcnt -ril $ril \
        -micr $micr -s {1 1 1}

    set nrate [$img nyq -tclReturn]

    set sampxy [expr int( 1000 * [lindex $nrate 0] ) ]
    set sampz [expr int( 1000 * [lindex $nrate 2] ) ]

    reportKeyValue "xy" $sampxy
    reportKeyValue "z" $sampz

    $img del
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


# Metadata reporter based on Huygens' preOpen operation.
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


# Script for reading in an image and output data in template form.
proc getMetaDataFromImage {} {

    set error [ getInputVariables {path filename} ]
    if { $error } { exit 1 }

    set img  [ hrmImgOpen $path $filename ]
    set dims [ $img getdims ]
    reportKeyValue "dims" $dims

    set templateStr [::Template::Micr::params2TemplateStr $img]    
    dict set templateDict params $templateStr

    dict map {key value} [dict get $templateDict params setp] {
        reportKeyValue $key $value
    }

    catch { $img del }
}


# Script for reading in a Huygens microscopy template and output template data.
proc getMetaDataFromHuTemplate {} {

    set error [ getInputVariables {huTemplate} ]
    if { $error } { exit 1 }

    set fp [open $huTemplate]
    set contents [read $fp]
    close $fp

    # The number of channels is not reported, extract it from the template.
    set pattern {^(.*) micr \{([a-z|\s]*)\}*}
    if { [regexp $pattern $contents match dummy1 micrList dummy2] } {
        set chanCnt [llength $micrList]
    } else {
        set chanCnt 1
    }

    # Let Huygens interpret the template. Notice that info up to 32 channels
    # is added automatically.
    set templateList [::Template::loadCommon "micr" $huTemplate outArr]

    # Convert the result to a dict.
    dict set templDict params $templateList

    # Stick to the channels from the original template. Discard the rest.
    dict map {key value} [dict get $templDict params setp] {

        # The sampling is the same for all channels.
        if {$key eq "s"} {
            reportKeyValue $key $value
        } else {
            reportKeyValue $key [lrange $value 0 [expr {$chanCnt - 1}]]
        }
    }
}

# Script for reading in a Huygens deconvolution template and output template data.
proc getDeconDataFromHuTemplate {} {

    set error [ getInputVariables {huTemplate} ]
    if { $error } { exit 1 }

    set fp [open $huTemplate]
    set contents [read $fp]
    close $fp

    # The number of channels is not reported, extract it from the template.
    set pattern {^(.*)taskList \{([a-z|\:|0-9|\s]*)\}*}
    if { [regexp $pattern $contents match dummy1 taskList dummy2] } {
        set chanCnt 0
        foreach item $taskList {
            set item [::Template::Decon::stripSuffix $item]
            if {$item ni {"cmle" "qmle" "gmle" "deconSkip"}} continue
            incr chanCnt
        }
        if {$chanCnt == 0} {
            incr chanCnt
        }
    } else {
        set chanCnt 1
    }

    # Let Huygens interpret the template. Notice that info up to 32 channels
    # is added automatically.
    set templateList [::Template::loadCommon "decon" $huTemplate outArr]

    # Stick to the channels from the original template. Discard the rest.
    foreach {dictKey dictValue} $templateList {

        set item [::Template::Decon::stripSuffix $dictKey]
        if {$item ni {"cmle" "qmle" "gmle" "deconSkip" "stabilize"
            "stabilize:post" "shift" "autocrop"}} {
            continue
        }
        reportKeyValue $dictKey $dictValue

        foreach {param value} $dictValue {
            if {$param ne "vector"} {
                set value [lindex $value 0]
            }
            reportKeyValue "$dictKey $param" $value
        }
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
    
    array set params [$srcImg setp -tclReturn]
    if {"generic" in $params(mType)} {
     	reportError "The image meta data specifies no microscope type.\
                     Impossible to continue."
    	return
    }

    
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
