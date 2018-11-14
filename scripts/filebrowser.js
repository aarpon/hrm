$( document ).ready(function() {

    count = 0;

    function traverse(obj, str, parent, index, level)
    {
        if ( Array.isArray(obj) ) {
            level++;
            str += "<ul>";
            obj.forEach(function(el, i) { str = traverse(el, str, obj, i, level); });
            str += "</ul>";
        }
        else if (Array.isArray(parent[index+1])) {
            var id = "id" + count++;
            str +=  '<input type="checkbox" id="' + id + '" /> \
                     <li><label for="' + id + '">' + obj + '</label></li>';
        }
        else {
            str += "<li>" + obj + "</li>";
        }

        return str;
    }

    function update_filelist(data)
    {
        var str = "<select size=20 multiple>";

        for (var i=0; i<data.length; i++)
            str += "<option>" + data[i] + "</option>";

        str += "</select>";

        document.getElementById("filelist").innerHTML = str;
    }

    $.getJSON( "php/dirtree.php", function( data ) {
        var str = traverse(data, "", null, 0, 0, 0);
        $("#dirtree").html(str);
    });


    $.getJSON( "php/filelist.php", function( data ) {
        update_filelist(data);
    });

    function get_path(li)
    {
        var str = "//";
        var l = $(li.parents("ul").get().reverse()).prev("li").children("label");
        l.each( function(i) { str += $(this).text() + "/"; });
        return str + li.text();
    }

    $("#dirtree").on("click", "li", function()
    {
        var path = get_path($(this));
        $("#path").text(path);
        $.getJSON( "php/filelist.php?dir=" + path, function( data ) { update_filelist(data); }); 
    });

});

