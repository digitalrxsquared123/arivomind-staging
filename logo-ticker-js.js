(function () {
  var SPEED = 40; // pixels per second (higher = faster)
  var tries = 0;
  var timer = setInterval(function () {
    tries++;
    if (tries > 50) { clearInterval(timer); return; }
    if (typeof jQuery === 'undefined') return;

    var $gallery = jQuery('.arivomind-icons-slider .uagb-slick-carousel.slick-initialized');
    if (!$gallery.length) return;

    clearInterval(timer);

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
  }, 200);
})();