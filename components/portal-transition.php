<!-- --- PORTAL TRANSITION HTML --- -->
  <div class="portal-overlay" id="portalOverlay" aria-hidden="true">
    <div class="portal-flash" id="portalFlash"></div>
    <div class="portal-rays" id="portalRays"></div>
    <svg class="portal-ring" id="portalRing" viewBox="0 0 200 200">
      <circle class="ring-outer" cx="100" cy="100" r="78" />
      <circle class="ring-inner" cx="100" cy="100" r="62" />
      <path class="ring-glyph" d="M100 18 L104 30 L116 32 L104 34 L100 46 L96 34 L84 32 L96 30 Z" />
      <path class="ring-glyph" d="M170 100 L162 104 L160 116 L158 104 L146 100 L158 96 L160 84 L162 96 Z" />
      <path class="ring-glyph" d="M30 100 L38 104 L40 116 L42 104 L54 100 L42 96 L40 84 L38 96 Z" />
    </svg>
    <div class="portal-sparks" id="portalSparks"></div>
    <div class="portal-side-host" id="portalSideHost"></div>
  </div>

  <script>
    const PET_REAL_PHOTOS = {
      Dog: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=1200&q=85&auto=format&fit=crop',
      Cat: 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=1200&q=85&auto=format&fit=crop',
      Bird: 'https://images.unsplash.com/photo-1452570053594-1b985d6ea890?w=1200&q=85&auto=format&fit=crop',
      Rabbit: 'https://images.unsplash.com/photo-1585110396000-c9ffd4e4b308?w=1200&q=85&auto=format&fit=crop',
      Fish: 'https://images.unsplash.com/photo-1522069169874-c58ec4b76be5?w=1200&q=85&auto=format&fit=crop',
      Reptile: 'https://images.unsplash.com/photo-1531386151447-fd76ad50012f?w=1200&q=85&auto=format&fit=crop',
      "Small Pets": 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?w=1200&q=85&auto=format&fit=crop',
      Other: 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=1200&q=85&auto=format&fit=crop',
    };
    function burstConfetti(btn) {
      btn.style.position = 'relative';
      btn.style.overflow = 'hidden';
      for (var i = 0; i < 14; i++) {
        var span = document.createElement('span');
        span.className = 'confetti-piece';
        var angle = Math.random() * Math.PI * 2;
        var dist = 40 + Math.random() * 60;
        var tx = Math.cos(angle) * dist;
        var ty = Math.sin(angle) * dist - 20;
        span.style.setProperty('--tx', tx + 'px');
        span.style.setProperty('--ty', ty + 'px');
        span.style.setProperty('--tr', (Math.random() * 360) + 'deg');
        span.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14"><ellipse cx="12" cy="15.2" rx="6" ry="5"/><ellipse cx="5" cy="8" rx="2.5" ry="3.1"/><ellipse cx="11.2" cy="5.2" rx="2.5" ry="3.1"/><ellipse cx="17.4" cy="8" rx="2.5" ry="3.1"/><ellipse cx="20" cy="12.4" rx="2.2" ry="2.9"/></svg>';
        btn.appendChild(span);
        setTimeout(function (s) { return function () { s.remove(); }; }(span), 950);
      }
    }

    function spawnPortalSparks() {
      var host = document.getElementById('portalSparks');
      host.innerHTML = '';
      var pawIcon = '<svg viewBox="0 0 24 24" width="100%" height="100%"><ellipse cx="12" cy="15.2" rx="6" ry="5"/><ellipse cx="5" cy="8" rx="2.5" ry="3.1"/><ellipse cx="11.2" cy="5.2" rx="2.5" ry="3.1"/><ellipse cx="17.4" cy="8" rx="2.5" ry="3.1"/><ellipse cx="20" cy="12.4" rx="2.2" ry="2.9"/></svg>';
      var starIcon = '<svg viewBox="0 0 24 24" width="100%" height="100%"><path d="M12 2 L14.2 9.8 L22 12 L14.2 14.2 L12 22 L9.8 14.2 L2 12 L9.8 9.8 Z"/></svg>';
      var colors = ['#FFF8EC', '#ffa95a', '#ff5a5a'];
      for (var i = 0; i < 22; i++) {
        var span = document.createElement('span');
        span.className = 'portal-spark';
        var angle = Math.random() * Math.PI * 2;
        var dist = 160 + Math.random() * 340;
        var tx = Math.cos(angle) * dist;
        var ty = Math.sin(angle) * dist;
        span.style.setProperty('--tx', tx + 'px');
        span.style.setProperty('--ty', ty + 'px');
        span.style.setProperty('--tr', (Math.random() * 360) + 'deg');
        span.style.color = colors[Math.floor(Math.random() * colors.length)];
        span.style.fill = 'currentColor';
        span.style.animationDelay = (Math.random() * 0.25) + 's';
        span.innerHTML = Math.random() < 0.6 ? pawIcon : starIcon;
        host.appendChild(span);
        setTimeout(function (s) { return function () { s.remove(); }; }(span), 1850);
      }
    }

    function spawnSideFlyers() {
      var host = document.getElementById('portalSideHost');
      host.innerHTML = '';
      var icons = {
        paw: '<svg viewBox="0 0 24 24" width="100%" height="100%"><ellipse cx="12" cy="15.2" rx="6" ry="5"/><ellipse cx="5" cy="8" rx="2.5" ry="3.1"/><ellipse cx="11.2" cy="5.2" rx="2.5" ry="3.1"/><ellipse cx="17.4" cy="8" rx="2.5" ry="3.1"/><ellipse cx="20" cy="12.4" rx="2.2" ry="2.9"/></svg>',
        bone: '<svg viewBox="0 0 24 24" width="100%" height="100%"><rect x="7" y="10.4" width="10" height="3.2" rx="1.4"/><circle cx="5" cy="9" r="2.7"/><circle cx="5" cy="15" r="2.7"/><circle cx="19" cy="9" r="2.7"/><circle cx="19" cy="15" r="2.7"/></svg>',
        star: '<svg viewBox="0 0 24 24" width="100%" height="100%"><path d="M12 2 L14.2 9.8 L22 12 L14.2 14.2 L12 22 L9.8 14.2 L2 12 L9.8 9.8 Z"/></svg>'
      };
      var palette = {
        paw: ['#ffa95a', '#ff5a5a'],
        bone: ['#FFF8EC'],
        star: ['#FFF8EC', '#ffa95a']
      };
      var keys = Object.keys(icons);
      var count = 16;
      for (var i = 0; i < count; i++) {
        var fromLeft = i % 2 === 0;
        var key = keys[Math.floor(Math.random() * keys.length)];
        var colors = palette[key];
        var span = document.createElement('span');
        span.className = 'portal-side';
        var size = 24 + Math.random() * 22;
        span.style.width = size + 'px';
        span.style.height = size + 'px';
        span.style.top = (6 + Math.random() * 84) + 'vh';
        if (fromLeft) { span.style.left = '-54px'; } else { span.style.right = '-54px'; }
        var travel = (window.innerWidth || 1200) * (0.42 + Math.random() * 0.32);
        var dx = fromLeft ? travel : -travel;
        var dy = (Math.random() - 0.5) * 180;
        var rot = (fromLeft ? 1 : -1) * (160 + Math.random() * 340);
        span.style.setProperty('--dx', dx + 'px');
        span.style.setProperty('--dy', dy + 'px');
        span.style.setProperty('--rot', rot + 'deg');
        span.style.color = colors[Math.floor(Math.random() * colors.length)];
        span.style.fill = 'currentColor';
        span.style.animationDelay = (Math.random() * 0.45) + 's';
        span.innerHTML = icons[key];
        host.appendChild(span);
        setTimeout(function (s) { return function () { s.remove(); }; }(span), 2650);
      }
    }

    function playPortalTransition(callback) {
      var overlay = document.getElementById('portalOverlay');
      var flash = document.getElementById('portalFlash');
      var rays = document.getElementById('portalRays');
      var ring = document.getElementById('portalRing');

      overlay.classList.add('active');
      flash.classList.add('run');
      rays.classList.add('run');
      ring.classList.add('run');
      spawnPortalSparks();
      spawnSideFlyers();

      setTimeout(function () {
        if (typeof callback === 'function') {
          callback();
        }
      }, 950);

      setTimeout(function () {
        overlay.classList.remove('active');
        flash.classList.remove('run');
        rays.classList.remove('run');
        ring.classList.remove('run');
      }, 2600);
    }
  </script>
  <!-- ------------------------------ -->
