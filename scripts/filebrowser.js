// https://scotch.io/tutorials/build-an-app-with-vue-js-a-lightweight-alternative-to-angularjs
// https://vuejsdevelopers.com/2017/10/23/vue-js-tree-menu-recursive-components/


var filetree_component = Vue.component('filetree', {
  template: `<div><ul>
                <li @click="toggleChildren">{{ label }}</li>
                <filetree v-if="showChildren" v-for="node in nodes" :nodes="node.nodes" :label="node.label"></filetree>
            </ul></div>`,
    props: [ 'label', 'nodes' ],
    name: 'filetree',
    data() {
     return {
       showChildren: false
     }
  },
  methods: {
    toggleChildren() {
       this.showChildren = !this.showChildren;
    }
  }
});

new Vue({

  // We want to target the div with an id of 'events'
  el: '#filebrowser',

  // Here we can register any values or collections that hold data
  // for the application
  data: {
    msg: "Hello Vue!",
    files: 0,
    mytree: {
  label: 'root',
  nodes: [
    {
      label: 'item1',
      nodes: [
        {
          label: 'item1.1'
        },
        {
          label: 'item1.2',
          nodes: [
            {
              label: 'item1.2.1'
            }
          ]
        }
      ]
    }, 
    {
      label: 'item2'  
    }
  ]
}
  },

  components: { filetree_component },

  // Anything within the ready function will run when the application loads
  mounted: function() {
        var vm = this;
        $.post( "ajax/filesystem.php?dirs=/", function( data ) {
            vm.tree = data;
        });
  },

  // Methods we want to use in our application are registered here
  methods: {}
});

