(function () {
  var PLACEHOLDER =
    "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

  var lightbox = document.getElementById("gallery-lightbox");
  if (!lightbox) return;

  var imgEl = lightbox.querySelector(".lightbox__img");
  var backdrop = lightbox.querySelector(".lightbox__backdrop");
  var closeBtn = lightbox.querySelector(".lightbox__close");
  var prevBtn = lightbox.querySelector(".lightbox__arrow--prev");
  var nextBtn = lightbox.querySelector(".lightbox__arrow--next");
  var triggers = document.querySelectorAll(".gallery-item__trigger");
  var lastFocus = null;
  var currentIndex = 0;

  function fullSizeUrl(src) {
    if (!src || src.indexOf("images.unsplash.com") === -1) {
      return src;
    }
    try {
      var u = new URL(src, window.location.href);
      u.searchParams.set("w", "1600");
      u.searchParams.set("q", "85");
      return u.toString();
    } catch (e) {
      return src;
    }
  }

  function showAt(index) {
    var n = triggers.length;
    if (n === 0) return;
    currentIndex = ((index % n) + n) % n;
    var trigger = triggers[currentIndex];
    var thumb = trigger.querySelector("img");
    if (!thumb) return;
    imgEl.src = fullSizeUrl(thumb.src);
    imgEl.alt = thumb.alt || "";
  }

  function open(trigger) {
    var idx = Array.prototype.indexOf.call(triggers, trigger);
    if (idx === -1) return;
    lastFocus = trigger;
    showAt(idx);
    lightbox.hidden = false;
    document.body.style.overflow = "hidden";
    closeBtn.focus({ preventScroll: true });
  }

  function goPrev() {
    showAt(currentIndex - 1);
  }

  function goNext() {
    showAt(currentIndex + 1);
  }

  function close() {
    lightbox.hidden = true;
    imgEl.src = PLACEHOLDER;
    imgEl.alt = "";
    document.body.style.overflow = "";
    if (lastFocus && typeof lastFocus.focus === "function") {
      lastFocus.focus({ preventScroll: true });
    }
  }

  triggers.forEach(function (btn) {
    btn.addEventListener("click", function () {
      open(btn);
    });
  });

  if (prevBtn) prevBtn.addEventListener("click", goPrev);
  if (nextBtn) nextBtn.addEventListener("click", goNext);

  backdrop.addEventListener("click", close);
  closeBtn.addEventListener("click", close);

  lightbox.addEventListener("click", function (e) {
    if (e.target === lightbox) close();
  });

  document.addEventListener("keydown", function (e) {
    if (lightbox.hidden) return;
    if (e.key === "Escape") {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === "ArrowLeft") {
      e.preventDefault();
      goPrev();
      return;
    }
    if (e.key === "ArrowRight") {
      e.preventDefault();
      goNext();
      return;
    }
  });
})();
