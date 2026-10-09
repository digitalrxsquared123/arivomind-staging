jQuery(window).on('load', function () {
  var $gallery = jQuery('.arivomind-icons-slider .uagb-slick-carousel');
  if (!$gallery.length || !$gallery.hasClass('slick-initialized')) return;

  // Turn off Slick for this gallery only
  $gallery.slick('unslick');

  // Build one strip: the logos, then a copy of them for a seamless loop
  var $items = $gallery.children();
  var $track = jQuery('<div class="arivo-marquee-track"></div>');
  $track.append($items);
  $track.append($items.clone().attr('aria-hidden', 'true'));

  $gallery.empty().append($track).addClass('arivo-marquee');
});