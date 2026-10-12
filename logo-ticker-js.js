(function () {
  var SPEED = 40; // pixels per second (higher = faster)
  var tries = 0;

  var timer = setInterval(function () {
    tries++;
    if (typeof jQuery === 'undefined') { if (tries > 50) clearInterval(timer); return; }

    // Build any gallery that Spectra has finished setting up
    jQuery('.arivomind-icons-slider .uagb-slick-carousel.slick-initialized')
      .not('.arivo-marquee')
      .each(function () { buildTicker(jQuery(this)); });

    // Stop checking once every gallery is done (or after 10 seconds)
    var remaining = jQuery('.arivomind-icons-slider .uagb-slick-carousel').not('.arivo-marquee').length;
    if (remaining === 0 || tries > 50) clearInterval(timer);
  }, 200);

  function buildTicker($gallery) {
    // Turn off Slick for this gallery only
    $gallery.slick('unslick');

    // Put one set of logos into a strip
    var $items = $gallery.children();
    var $track = jQuery('<div class="arivo-marquee-track"></div>');
    $track.append($items);
    $gallery.empty().append($track).addClass('arivo-marquee');

    // Measure one set, then add copies until the strip covers the widest screen plus one set
    var setWidth = $track[0].scrollWidth;
    if (!setWidth) return;
    var needed = setWidth + Math.max(window.screen.width, $gallery.width());
    var guard = 0;
    while ($track[0].scrollWidth < needed && guard < 20) {
      $track.append($items.clone().attr('aria-hidden', 'true'));
      guard++;
    }

    // Slide exactly one set, at a steady speed
    $track[0].style.setProperty('--arivo-set-width', setWidth + 'px');
    $track[0].style.setProperty('--arivo-duration', (setWidth / SPEED) + 's');
  }
})();