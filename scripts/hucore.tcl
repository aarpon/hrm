# This file is part of huygens remote manager.

# This script contains some procedures that report throught stdout to the HRM
# about image properties. They may also write thumbnails in the future.

# ---------------------------------------------------------------------------

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
        puts "ERROR"
        puts "Wrong arguments.\
            Use indexed options -img_# to pass the image list to hucore.\
            The number of images must be passed with option -count.\
            The directory that contains them must be passed with -dir."
        exit 1
    }

    for {set i 0} {$i < $imgCount} {incr i} {

        set image [Hu_getOpt -img_$i]

        if { $image == -1 } {
            puts "ERROR"
            puts "Wrong arguments.\
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
            puts "ERROR"
            puts "Unexisting image '$image'. "
            continue
        }


        if { [isMultiImgFile $path] } {
            puts "TYPE"
            puts "multiple"
            set error [ catch { \
                img preOpen $path } contents]
            if { $error } {
                puts "ERROR"
                puts "Can't find subimages for $image: $contents"
            } else {
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


# ----------------   MAIN routine -----------------
# Execute selected procedure

set tool [Hu_getOpt -tool]

if { $tool == -1 } {
    puts "Wrong arguments.\
        Use option -tool to specify the utility procedure to execute."
    exit 1
}

# Execute procedure
puts "BEGIN PROC"
puts "$tool"
eval $tool
puts "END PROC"
exit 0
