// https://scotch.io/tutorials/build-an-app-with-vue-js-a-lightweight-alternative-to-angularjs
// https://vuejsdevelopers.com/2017/10/23/vue-js-tree-menu-recursive-components/


var filetree_component = Vue.component('filetree', {
  template: `<div><ul>
                <li @click="toggleChildren">{{ label }}</li>
                <filetree v-if="showChildren" v-for="(item, key, index) in nodes" :nodes="item" :label="key" :path="path + '/' + key"></filetree>
            </ul></div>`,
    props: { label: {type: String, default:'/'}, nodes: {}, path: {default: ""} },
    name: 'filetree',
    data() {
     return {
       showChildren: false,
     }
    },
  methods: {
    toggleChildren() {
        console.log(this.path);
        $.getJSON( "ajax/filesystem.php?ls=" + this.path.substring(1), function( data ) {
            vm.files = data;
        });
       this.showChildren = !this.showChildren;
    }
  }
});

var filelist_component = Vue.component('filelist', {
  template: `<div><table><tr><th>File name</th><th>Last modified</th></tr><tr v-for="file in files"><td>{{ file.name }}</td><td>{{ file.mtime }}</td></tr></table></div>`,
    props: [ 'files' ],
    name: 'filelist',
});

var filepreview_component = Vue.component('filepreview', {
  template: `<div><table><tr v-for="file in files"><td>{{ file.name }}</td></tr></table></div>`,
    props: [ 'files' ],
    name: 'filelist',
});

var vm = new Vue({

  // We want to target the div with an id of 'events'
  el: '#filebrowser',

  // Here we can register any values or collections that hold data
  // for the application
  data: {
    tree: 0,
    files: 0,
  },

  components: { filetree_component, filelist_component },

  // Anything within the ready function will run when the application loads
  mounted: function() {
        $.getJSON( "ajax/filesystem.php?dirs=/", function( data ) {
            vm.tree = data['/'];
        });
        $.getJSON( "ajax/filesystem.php?ls", function( data ) {
            vm.files = data;
        });
  },

  // Methods we want to use in our application are registered here
  methods: {}
});

