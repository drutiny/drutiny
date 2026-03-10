(function ($) {
  $(document).ready(function() {
    $('code').not('p code').each(function(i, block) {
      hljs.highlightBlock(block);
    });
  });
})(jQuery);
