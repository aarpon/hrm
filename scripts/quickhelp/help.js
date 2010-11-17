// Function that updates the text on mouseover
// Must be added to the page along to the corresponding pagenameHelp.js file

function changeQuickHelp( id ) {
  smoothChangeDivCond( id, 'contextHelp', window.helpText[ id ], 300 );
}
