
const PET_MASCOTS = {
  'dog': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
                      <g class="tail" style="transform-origin: 88px 86px;">
                        <ellipse cx="92" cy="92" rx="10" ry="5" fill="#F2A93B" transform="rotate(20 88 86)" />
                      </g>
                      <ellipse cx="60" cy="86" rx="33" ry="26" fill="#F2A93B" />
                      <ellipse cx="60" cy="93" rx="18" ry="14" fill="#FFF8EC" />
                      <g class="ear-l" style="transform-origin: 36px 30px;">
                        <ellipse cx="35" cy="27" rx="11" ry="17" fill="#F2A93B" transform="rotate(-18 35 27)" />
                        <ellipse cx="35" cy="29" rx="5" ry="9" fill="#FF7A5C" transform="rotate(-18 35 29)" />
                      </g>
                      <g class="ear-r" style="transform-origin: 84px 30px;">
                        <ellipse cx="85" cy="27" rx="11" ry="17" fill="#F2A93B" transform="rotate(18 85 27)" />
                        <ellipse cx="85" cy="29" rx="5" ry="9" fill="#FF7A5C" transform="rotate(18 85 29)" />
                      </g>
                      <circle cx="60" cy="49" r="29" fill="#F2A93B" />
                      <circle cx="49" cy="47" r="9" fill="#FFF8EC" />
                      <circle cx="71" cy="47" r="9" fill="#FFF8EC" />
                      <circle class="pupil pupil-l" cx="49" cy="47" r="4" fill="#2B2420" />
                      <circle class="pupil pupil-r" cx="71" cy="47" r="4" fill="#2B2420" />
                      <rect class="lids" x="40" y="42" width="40" height="11" fill="#F2A93B" />
                      <ellipse cx="60" cy="63" rx="14" ry="10" fill="#FFF8EC" />
                      <ellipse cx="60" cy="58" rx="5" ry="3.5" fill="#2B2420" />
                      <path d="M 60 61.5 V 66 M 53 65 Q 56.5 69 60 66 M 60 66 Q 63.5 69 67 65" stroke="#2B2420" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                      <ellipse class="paw-l" cx="47" cy="104" rx="13" ry="9" fill="#FFF8EC"
                        style="transition: all 0.3s ease; transform-origin: 47px 104px;" />
                      <ellipse class="paw-r" cx="73" cy="104" rx="13" ry="9" fill="#FFF8EC"
                        style="transition: all 0.3s ease; transform-origin: 73px 104px;" />
                    </svg>`,
    config: {}
  },
  'cat': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Tail: thick rounded stroke, curls at the tip -->
        <path class="tail" d="M88 96 C 104 93 112 80 105 67" stroke="#FF5CA8" stroke-width="11" fill="none" stroke-linecap="round" style="transform-origin: 88px 96px;" />

        <!-- Body -->
        <ellipse cx="60" cy="88" rx="32" ry="25" fill="#FF5CA8" />
        <ellipse cx="60" cy="95" rx="16" ry="12" fill="#FFF8EC" />

        <!-- Ears: pointed triangles -->
        <path class="ear-l" d="M30 30 L24 8 L43 22 Z" fill="#FF5CA8" style="transform-origin: 33px 24px;" />
        <path class="ear-r" d="M90 30 L96 8 L77 22 Z" fill="#FF5CA8" style="transform-origin: 87px 24px;" />
        <path d="M30 26 L27 14 L37 21 Z" fill="#D43E86" />
        <path d="M90 26 L93 14 L83 21 Z" fill="#D43E86" />

        <!-- Head -->
        <circle cx="60" cy="50" r="28" fill="#FF5CA8" />

        <!-- Eyes -->
        <circle cx="49" cy="48" r="8.5" fill="#FFF8EC" />
        <circle cx="71" cy="48" r="8.5" fill="#FFF8EC" />
        <ellipse class="pupil pupil-l" cx="49" cy="48" rx="2.6" ry="5.5" fill="#2B2420" />
        <ellipse class="pupil pupil-r" cx="71" cy="48" rx="2.6" ry="5.5" fill="#2B2420" />
        <rect class="lids" x="40" y="43" width="40" height="11" fill="#FF5CA8" />

        <!-- Muzzle, nose, mouth -->
        <ellipse cx="60" cy="60" rx="10" ry="7" fill="#FFF8EC" />
        <path d="M57 56 L63 56 L60 60 Z" fill="#2B2420" />
        <path d="M 60 60 Q 55 64 51 61 M 60 60 Q 65 64 69 61" stroke="#2B2420" stroke-width="1.4" fill="none" stroke-linecap="round" />

        <!-- Whiskers (subtle idle twitch via .whiskers) -->
        <g class="whiskers" style="transform-origin: 44px 60px;">
          <line x1="44" y1="57" x2="26" y2="53" stroke="#2B2420" stroke-width="1" opacity="0.55" />
          <line x1="44" y1="60" x2="25" y2="60" stroke="#2B2420" stroke-width="1" opacity="0.55" />
          <line x1="44" y1="63" x2="26" y2="67" stroke="#2B2420" stroke-width="1" opacity="0.55" />
        </g>
        <g class="whiskers" style="transform-origin: 76px 60px;">
          <line x1="76" y1="57" x2="94" y2="53" stroke="#2B2420" stroke-width="1" opacity="0.55" />
          <line x1="76" y1="60" x2="95" y2="60" stroke="#2B2420" stroke-width="1" opacity="0.55" />
          <line x1="76" y1="63" x2="94" y2="67" stroke="#2B2420" stroke-width="1" opacity="0.55" />
        </g>

        <!-- Paws -->
        <ellipse class="paw-l" cx="47" cy="104" rx="12" ry="8" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 47px 104px;" />
        <ellipse class="paw-r" cx="73" cy="104" rx="12" ry="8" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 73px 104px;" />
      </svg>`,
    config: { cover: { l: 'translate(2px, -56px) rotate(15deg)', r: 'translate(-2px, -56px) rotate(-15deg)' } }
  },
  'bird': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Tail: fan of feathers -->
        <g class="tail" style="transform-origin: 92px 88px;">
          <ellipse cx="92" cy="86" rx="6" ry="13" fill="#1480B8" />
          <ellipse cx="100" cy="84" rx="6" ry="13" fill="#1AA7EC" transform="rotate(28 100 84)" />
          <ellipse cx="86" cy="84" rx="6" ry="13" fill="#1AA7EC" transform="rotate(-22 86 84)" />
        </g>

        <!-- Body -->
        <ellipse cx="60" cy="86" rx="30" ry="27" fill="#1AA7EC" />
        <ellipse cx="60" cy="94" rx="16" ry="15" fill="#FFF8EC" />

        <!-- Crest feathers (ear-perk equivalent) -->
        <g class="ear-l" style="transform-origin: 54px 30px;">
          <ellipse cx="54" cy="22" rx="4" ry="14" fill="#1AA7EC" transform="rotate(-25 54 22)" />
        </g>
        <g class="ear-r" style="transform-origin: 66px 30px;">
          <ellipse cx="66" cy="22" rx="4" ry="14" fill="#1AA7EC" transform="rotate(25 66 22)" />
        </g>
        <ellipse cx="60" cy="18" rx="4" ry="16" fill="#1480B8" />

        <!-- Head -->
        <circle cx="60" cy="50" r="27" fill="#1AA7EC" />

        <!-- Eyes -->
        <circle cx="50" cy="48" r="8" fill="#FFF8EC" />
        <circle cx="70" cy="48" r="8" fill="#FFF8EC" />
        <circle class="pupil pupil-l" cx="50" cy="48" r="3.6" fill="#2B2420" />
        <circle class="pupil pupil-r" cx="70" cy="48" r="3.6" fill="#2B2420" />
        <rect class="lids" x="41" y="43" width="38" height="10" fill="#1AA7EC" />

        <!-- Beak -->
        <path d="M48 58 Q60 51 72 58 Q60 64 48 58 Z" fill="#F2A93B" stroke="#2B2420" stroke-width="1" />
        <path d="M52 60 Q60 65 68 60 Q60 67 52 60 Z" fill="#E09322" stroke="#2B2420" stroke-width="0.8" />

        <!-- Wings (paw-l/paw-r equivalent), folded down at the sides -->
        <g class="paw-l" style="transition: all 0.3s ease; transform-origin: 35px 90px;">
          <ellipse cx="35" cy="90" rx="12" ry="20" fill="#1AA7EC" stroke="#1480B8" stroke-width="1.5" transform="rotate(-25 35 90)" />
        </g>
        <g class="paw-r" style="transition: all 0.3s ease; transform-origin: 85px 90px;">
          <ellipse cx="85" cy="90" rx="12" ry="20" fill="#1AA7EC" stroke="#1480B8" stroke-width="1.5" transform="rotate(25 85 90)" />
        </g>
      </svg>`,
    config: { cover: { l: 'translate(14px, -44px) rotate(48deg)', r: 'translate(-14px, -44px) rotate(-48deg)' } }
  },
  'rabbit': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <g class="tail" style="animation: pcFluff 1.6s ease-in-out infinite; transform-origin: 94px 92px;">
            <circle cx="94" cy="92" r="8" fill="#FFF8EC" />
        </g>
        <ellipse cx="60" cy="88" rx="31" ry="25" fill="#7ECF6B" />
        <ellipse cx="60" cy="95" rx="17" ry="13" fill="#FFF8EC" />
        <g class="ear-l" style="transform-origin: 38px 36px;">
            <ellipse cx="38" cy="22" rx="7" ry="20" fill="#7ECF6B" />
            <ellipse cx="38" cy="22" rx="3.4" ry="15" fill="#5BAE48" />
        </g>
        <g class="ear-r" style="transform-origin: 82px 36px;">
            <ellipse cx="82" cy="22" rx="7" ry="20" fill="#7ECF6B" />
            <ellipse cx="82" cy="22" rx="3.4" ry="15" fill="#5BAE48" />
        </g>
        <circle cx="60" cy="54" r="27" fill="#7ECF6B" />
        <circle cx="40" cy="62" r="9" fill="#FFF8EC" opacity="0.55" />
        <circle cx="80" cy="62" r="9" fill="#FFF8EC" opacity="0.55" />

        <!-- Eyes -->
        <circle cx="49" cy="50" r="8.5" fill="#FFF8EC" />
        <circle cx="71" cy="50" r="8.5" fill="#FFF8EC" />
        <circle class="pupil pupil-l" cx="49" cy="50" r="3.8" fill="#2B2420" />
        <circle class="pupil pupil-r" cx="71" cy="50" r="3.8" fill="#2B2420" />
        <rect class="lids" x="41" y="45" width="38" height="10" fill="#7ECF6B" />

        <!-- Nose + buck teeth -->
        <path d="M57 60 L63 60 L60 64 Z" fill="#2B2420" />
        <rect x="56.5" y="65" width="3" height="6" rx="1" fill="#FFF8EC" stroke="#2B2420" stroke-width="0.6" />
        <rect x="60.5" y="65" width="3" height="6" rx="1" fill="#FFF8EC" stroke="#2B2420" stroke-width="0.6" />

        <!-- Paws -->
        <ellipse class="paw-l" cx="47" cy="104" rx="13" ry="9" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 47px 104px;" />
        <ellipse class="paw-r" cx="73" cy="104" rx="13" ry="9" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 73px 104px;" />
      </svg>`,
    config: { cover: { l: 'translate(2px, -54px) rotate(15deg)', r: 'translate(-2px, -54px) rotate(-15deg)' } }
  },
  'fish': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Idle bubbles -->
        <circle class="bubble" cx="86" cy="40" r="2.5" fill="#FFF8EC" style="animation-delay: 0s;" />
        <circle class="bubble" cx="92" cy="46" r="1.6" fill="#FFF8EC" style="animation-delay: 1.1s;" />
        <circle class="bubble" cx="80" cy="34" r="1.8" fill="#FFF8EC" style="animation-delay: 2.1s;" />

        <!-- Tail fin -->
        <path class="tail" d="M88 78 L108 62 L101 78 L108 94 Z" fill="#14C8D8" style="transform-origin: 88px 78px;" />

        <!-- Body -->
        <ellipse cx="58" cy="78" rx="30" ry="22" fill="#14C8D8" />
        <ellipse cx="58" cy="84" rx="20" ry="10" fill="#FFF8EC" />

        <!-- Dorsal fin -->
        <path d="M48 56 Q60 40 72 56 Z" fill="#0E9CAA" />

        <!-- Eyes -->
        <circle cx="46" cy="54" r="9" fill="#FFF8EC" />
        <circle cx="68" cy="54" r="9" fill="#FFF8EC" />
        <circle class="pupil pupil-l" cx="46" cy="54" r="4" fill="#2B2420" />
        <circle class="pupil pupil-r" cx="68" cy="54" r="4" fill="#2B2420" />
        <rect class="lids" x="37" y="49" width="40" height="10" fill="#14C8D8" />

        <!-- Mouth -->
        <ellipse cx="56" cy="68" rx="3.6" ry="2.8" fill="none" stroke="#2B2420" stroke-width="1.4" />

        <!-- Gill lines -->
        <path d="M30 58 Q34 62 30 66" stroke="#0E9CAA" stroke-width="1.4" fill="none" opacity="0.6" />
        <path d="M26 56 Q31 62 26 68" stroke="#0E9CAA" stroke-width="1.2" fill="none" opacity="0.4" />

        <!-- Pectoral fins (paw-l/paw-r equivalent) -->
        <ellipse class="paw-l" cx="30" cy="76" rx="8" ry="14" fill="#0E9CAA" transform="rotate(-15 30 76)" style="transition: all 0.3s ease; transform-origin: 30px 76px;" />
        <ellipse class="paw-r" cx="86" cy="76" rx="8" ry="14" fill="#0E9CAA" transform="rotate(15 86 76)" style="transition: all 0.3s ease; transform-origin: 86px 76px;" />
      </svg>`,
    config: { cover: { l: 'translate(18px, -24px) rotate(55deg)', r: 'translate(-18px, -24px) rotate(-55deg)' } }
  },
  'reptile': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Tail: long taper -->
        <path class="tail" d="M88 92 Q108 90 112 74 Q113 67 107 63" stroke="#19BF84" stroke-width="9" fill="none" stroke-linecap="round" style="transform-origin: 88px 92px;" />

        <!-- Hint of back legs peeking out -->
        <ellipse cx="28" cy="96" rx="5" ry="6" fill="#19BF84" />
        <ellipse cx="92" cy="96" rx="5" ry="6" fill="#19BF84" />

        <!-- Body -->
        <ellipse cx="60" cy="86" rx="30" ry="24" fill="#19BF84" />
        <ellipse cx="60" cy="92" rx="16" ry="12" fill="#FFF8EC" />

        <!-- Back scale texture -->
        <ellipse cx="48" cy="68" rx="3" ry="5" fill="#117A55" opacity="0.7" />
        <ellipse cx="60" cy="64" rx="3" ry="5" fill="#117A55" opacity="0.7" />
        <ellipse cx="72" cy="68" rx="3" ry="5" fill="#117A55" opacity="0.7" />

        <!-- Brow ridges (ear-perk equivalent) -->
        <ellipse class="ear-l" cx="46" cy="32" rx="6" ry="4" fill="#19BF84" style="transform-origin: 46px 34px;" />
        <ellipse class="ear-r" cx="74" cy="32" rx="6" ry="4" fill="#19BF84" style="transform-origin: 74px 34px;" />

        <!-- Head -->
        <circle cx="60" cy="48" r="26" fill="#19BF84" />

        <!-- Big eyes, horizontal slit pupils -->
        <circle cx="48" cy="47" r="9.5" fill="#FFF8EC" />
        <circle cx="72" cy="47" r="9.5" fill="#FFF8EC" />
        <ellipse class="pupil pupil-l" cx="48" cy="47" rx="5.5" ry="2" fill="#2B2420" />
        <ellipse class="pupil pupil-r" cx="72" cy="47" rx="5.5" ry="2" fill="#2B2420" />
        <rect class="lids" x="38" y="41" width="44" height="12" fill="#19BF84" />

        <!-- Snout, nostrils, mouth -->
        <ellipse cx="60" cy="60" rx="9" ry="6" fill="#FFF8EC" />
        <circle cx="56" cy="57" r="1" fill="#2B2420" />
        <circle cx="64" cy="57" r="1" fill="#2B2420" />
        <path d="M52 62 Q60 65 68 62" stroke="#2B2420" stroke-width="1.3" fill="none" stroke-linecap="round" />

        <!-- Paws, with tiny sticky toe pads -->
        <ellipse class="paw-l" cx="46" cy="100" rx="10" ry="7" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 46px 100px;" />
        <ellipse class="paw-r" cx="74" cy="100" rx="10" ry="7" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 74px 100px;" />
        <circle cx="41" cy="103" r="1.4" fill="#19BF84" opacity="0.6" />
        <circle cx="46" cy="105" r="1.4" fill="#19BF84" opacity="0.6" />
        <circle cx="51" cy="103" r="1.4" fill="#19BF84" opacity="0.6" />
        <circle cx="69" cy="103" r="1.4" fill="#19BF84" opacity="0.6" />
        <circle cx="74" cy="105" r="1.4" fill="#19BF84" opacity="0.6" />
        <circle cx="79" cy="103" r="1.4" fill="#19BF84" opacity="0.6" />
      </svg>`,
    config: { cover: { l: 'translate(2px, -53px) rotate(15deg)', r: 'translate(-2px, -53px) rotate(-15deg)' }, lidsClosedHeight: 12, lidsFocusedHeight: 32 }
  },
  'horse': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Tail: flowing, two-tone strands -->
        <g class="tail" style="transform-origin: 90px 88px;">
          <ellipse cx="94" cy="98" rx="4" ry="17" fill="#C17B3F" transform="rotate(8 94 98)" />
          <ellipse cx="99" cy="96" rx="3.5" ry="15" fill="#FFF8EC" transform="rotate(16 99 96)" />
          <ellipse cx="89" cy="98" rx="3.5" ry="15" fill="#8C5A2C" transform="rotate(-4 89 98)" />
        </g>

        <!-- Body -->
        <ellipse cx="60" cy="88" rx="33" ry="25" fill="#C17B3F" />
        <ellipse cx="60" cy="95" rx="18" ry="13" fill="#FFF8EC" />

        <!-- Mane -->
        <ellipse cx="42" cy="38" rx="6" ry="12" fill="#8C5A2C" transform="rotate(-20 42 38)" />
        <ellipse cx="48" cy="30" rx="6" ry="12" fill="#FFF8EC" transform="rotate(-10 48 30)" />
        <ellipse cx="56" cy="26" rx="6" ry="12" fill="#8C5A2C" transform="rotate(-2 56 26)" />

        <!-- Ears -->
        <ellipse class="ear-l" cx="42" cy="22" rx="6" ry="13" fill="#C17B3F" transform="rotate(-12 42 22)" style="transform-origin: 42px 32px;" />
        <ellipse class="ear-r" cx="78" cy="22" rx="6" ry="13" fill="#C17B3F" transform="rotate(12 78 22)" style="transform-origin: 78px 32px;" />
        <ellipse cx="42" cy="24" rx="2.8" ry="8" fill="#8C5A2C" transform="rotate(-12 42 24)" />
        <ellipse cx="78" cy="24" rx="2.8" ry="8" fill="#8C5A2C" transform="rotate(12 78 24)" />

        <!-- Head + elongated snout -->
        <ellipse cx="60" cy="50" rx="24" ry="22" fill="#C17B3F" />
        <ellipse cx="60" cy="70" rx="14" ry="14" fill="#C17B3F" />
        <ellipse cx="60" cy="76" rx="10" ry="9" fill="#FFF8EC" />

        <!-- Eyes -->
        <circle cx="44" cy="46" r="8" fill="#FFF8EC" />
        <circle cx="76" cy="46" r="8" fill="#FFF8EC" />
        <circle class="pupil pupil-l" cx="44" cy="46" r="3.5" fill="#2B2420" />
        <circle class="pupil pupil-r" cx="76" cy="46" r="3.5" fill="#2B2420" />
        <rect class="lids" x="36" y="41" width="48" height="10" fill="#C17B3F" />

        <!-- Nostrils + mouth -->
        <ellipse cx="54" cy="76" rx="2" ry="3" fill="#2B2420" />
        <ellipse cx="66" cy="76" rx="2" ry="3" fill="#2B2420" />
        <path d="M52 84 Q60 87 68 84" stroke="#2B2420" stroke-width="1.4" fill="none" stroke-linecap="round" />

        <!-- Front legs / hooves (paw-l/paw-r equivalent) -->
        <ellipse class="paw-l" cx="46" cy="106" rx="10" ry="8" fill="#8C5A2C" style="transition: all 0.3s ease; transform-origin: 46px 106px;" />
        <ellipse class="paw-r" cx="74" cy="106" rx="10" ry="8" fill="#8C5A2C" style="transition: all 0.3s ease; transform-origin: 74px 106px;" />
        <ellipse cx="46" cy="100" rx="10" ry="4" fill="#FFF8EC" />
        <ellipse cx="74" cy="100" rx="10" ry="4" fill="#FFF8EC" />
      </svg>`,
    config: { cover: { l: 'translate(3px, -60px) rotate(12deg)', r: 'translate(-3px, -60px) rotate(-12deg)' }, lidsClosedHeight: 10, lidsFocusedHeight: 26 }
  },
  'small pets': {
    svg: `<svg viewBox="0 0 120 120" width="100%" height="100%">
        <!-- Tail: tiny stub -->
        <ellipse class="tail" cx="88" cy="98" rx="4" ry="3" fill="#9D6CFF" style="transform-origin: 88px 98px;" />

        <!-- Body: extra round -->
        <ellipse cx="60" cy="88" rx="34" ry="27" fill="#9D6CFF" />
        <ellipse cx="60" cy="96" rx="18" ry="14" fill="#FFF8EC" />

        <!-- Stuffed cheeks -->
        <circle class="cheek-l" cx="38" cy="58" r="12" fill="#9D6CFF" style="transform-origin: 38px 58px;" />
        <circle class="cheek-r" cx="82" cy="58" r="12" fill="#9D6CFF" style="transform-origin: 82px 58px;" />

        <!-- Ears -->
        <circle class="ear-l" cx="38" cy="26" r="9" fill="#9D6CFF" style="transform-origin: 38px 30px;" />
        <circle class="ear-r" cx="82" cy="26" r="9" fill="#9D6CFF" style="transform-origin: 82px 30px;" />
        <circle cx="38" cy="26" r="5" fill="#6F46C9" />
        <circle cx="82" cy="26" r="5" fill="#6F46C9" />

        <!-- Head -->
        <circle cx="60" cy="52" r="24" fill="#9D6CFF" />

        <!-- Eyes -->
        <circle cx="49" cy="50" r="7.5" fill="#FFF8EC" />
        <circle cx="71" cy="50" r="7.5" fill="#FFF8EC" />
        <circle class="pupil pupil-l" cx="49" cy="50" r="3.5" fill="#2B2420" />
        <circle class="pupil pupil-r" cx="71" cy="50" r="3.5" fill="#2B2420" />
        <rect class="lids" x="42" y="45" width="36" height="9" fill="#9D6CFF" />

        <!-- Nose + small whiskers -->
        <ellipse cx="60" cy="62" rx="3" ry="2.2" fill="#2B2420" />
        <line x1="50" y1="62" x2="38" y2="60" stroke="#2B2420" stroke-width="0.9" opacity="0.5" />
        <line x1="50" y1="65" x2="38" y2="66" stroke="#2B2420" stroke-width="0.9" opacity="0.5" />
        <line x1="70" y1="62" x2="82" y2="60" stroke="#2B2420" stroke-width="0.9" opacity="0.5" />
        <line x1="70" y1="65" x2="82" y2="66" stroke="#2B2420" stroke-width="0.9" opacity="0.5" />

        <!-- Paws -->
        <ellipse class="paw-l" cx="47" cy="106" rx="10" ry="7" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 47px 106px;" />
        <ellipse class="paw-r" cx="73" cy="106" rx="10" ry="7" fill="#FFF8EC" style="transition: all 0.3s ease; transform-origin: 73px 106px;" />
      </svg>`,
    config: { cover: { l: 'translate(2px, -56px) rotate(15deg)', r: 'translate(-2px, -56px) rotate(-15deg)' } }
  },
};

function initPetMascot(config) {
  var mascot = document.getElementById(config.mascotId);
  var pwInput = document.getElementById(config.passwordId);
  if (!mascot || !pwInput) return null;

  var eyeToggle = config.eyeToggleId ? document.getElementById(config.eyeToggleId) : null;
  var nameInput = config.nameId ? document.getElementById(config.nameId) : null;

  var pawL = mascot.querySelector('.paw-l');
  var pawR = mascot.querySelector('.paw-r');
  var lids = mascot.querySelector('.lids');
  var pupils = mascot.querySelectorAll('.pupil');

  var coverL = (config.cover && config.cover.l) || 'translate(2px, -57px) rotate(15deg)';
  var coverR = (config.cover && config.cover.r) || 'translate(-2px, -57px) rotate(-15deg)';
  var lidsClosedHeight = (config.lidsClosedHeight != null) ? config.lidsClosedHeight : 11;
  var lidsFocusedHeight = (config.lidsFocusedHeight != null) ? config.lidsFocusedHeight : 28;

  // Initialize eyelids to open
  if (lids) lids.style.transform = 'scaleY(0)';

  // 1. Eye tracking
  function onMouseMove(e) {
    if (pwInput.type === 'text') return; // don't track while eyes are covered/shown
    var rect = mascot.getBoundingClientRect();
    var centerX = rect.left + rect.width / 2;
    var centerY = rect.top + rect.height / 2;
    var maxMove = 3;
    var moveX = Math.max(-maxMove, Math.min(maxMove, (e.clientX - centerX) / 50));
    var moveY = Math.max(-maxMove, Math.min(maxMove, (e.clientY - centerY) / 50));
    pupils.forEach(function (p) {
      p.style.transform = 'translate(' + moveX + 'px, ' + moveY + 'px)';
    });
  }
  document.addEventListener('mousemove', onMouseMove);

  // 2. Paw/wing/fin covering logic
  function updateCover() {
    clearTimeout(mascot.lidsTimer);
    if (pwInput.type === 'password' && pwInput.value.length > 0) {
      if (pawL) pawL.style.transform = coverL;
      if (pawR) pawR.style.transform = coverR;
      if (lids) {
        mascot.lidsTimer = setTimeout(function() {
          lids.style.transform = 'scaleY(1)';
        }, 120);
      }
    } else {
      if (pawL) pawL.style.transform = '';
      if (pawR) pawR.style.transform = '';
      if (lids) {
        var focused = document.activeElement === pwInput && pwInput.type === 'password';
        lids.style.transform = focused ? 'scaleY(0.4)' : 'scaleY(0)';
      }
    }
  }
  pwInput.addEventListener('input', updateCover);
  pwInput.addEventListener('focus', updateCover);
  pwInput.addEventListener('blur', function () {
    clearTimeout(mascot.lidsTimer);
    if (pawL) pawL.style.transform = '';
    if (pawR) pawR.style.transform = '';
    if (lids) lids.style.transform = 'scaleY(0)';
  });

  if (eyeToggle) {
    eyeToggle.addEventListener('click', function () {
      pwInput.type = (pwInput.type === 'text') ? 'password' : 'text';
      // (your own icon-swap logic goes here, same as the dog implementation)
      updateCover();
    });
  }

  // 3. Ear-perk on name focus (harmless no-op for species without .ear-l/.ear-r)
  if (nameInput) {
    nameInput.addEventListener('focus', function () { mascot.classList.add('perk'); });
    nameInput.addEventListener('blur', function () { mascot.classList.remove('perk'); });
  }

  // Public handle, e.g. call .setExcited(true) on a successful signup
  return {
    setExcited: function (on) { mascot.classList.toggle('excited', !!on); },
    destroy: function () { document.removeEventListener('mousemove', onMouseMove); }
  };
}


let currentMascotInstances = {};

function mountMascot(containerId, passwordId, toggleId, nameId, petType) {
  // Clean up existing instance if any
  if (currentMascotInstances[containerId]) {
    currentMascotInstances[containerId].destroy();
    delete currentMascotInstances[containerId];
  }

  var container = document.getElementById(containerId);
  if (!container) return;

  var typeKey = (petType || '').toLowerCase().trim();
  var mascotData = PET_MASCOTS[typeKey];

  if (!mascotData || !mascotData.svg) {
    // Other or unmapped pet type -> blank mascot
    container.innerHTML = '';
    return;
  }

  // Inject SVG with a smooth transition if already mounted
  if (container.innerHTML.trim() !== '') {
    container.style.transition = 'opacity 0.25s ease-out';
    container.style.opacity = '0';
    setTimeout(function() {
      container.innerHTML = mascotData.svg;
      var config = Object.assign({
        mascotId: containerId,
        passwordId: passwordId,
        eyeToggleId: toggleId,
        nameId: nameId
      }, mascotData.config);
      currentMascotInstances[containerId] = initPetMascot(config);
      
      container.style.transition = 'opacity 0.25s ease-in';
      container.style.opacity = '1';
    }, 250);
  } else {
    container.innerHTML = mascotData.svg;
    var config = Object.assign({
      mascotId: containerId,
      passwordId: passwordId,
      eyeToggleId: toggleId,
      nameId: nameId
    }, mascotData.config);
    currentMascotInstances[containerId] = initPetMascot(config);
  }
}

    // Setup default mascots
    mountMascot('mascot', 'reg-password', 'eyeToggle', 'reg-name', '');

    // Hook up signup dropdown
    var signupPetTypeSelect = document.getElementById('reg-pet_type');
    if (signupPetTypeSelect) {
      signupPetTypeSelect.addEventListener('change', function(e) {
        mountMascot('mascot', 'reg-password', 'eyeToggle', 'reg-name', e.target.value);
      });
    }

      // Paw mouse trail for sign up
      var den = document.getElementById('den');
      if (den) {
        var lastTrace = 0;
        den.addEventListener('mousemove', function (e) {
          var now = Date.now();
          if (now - lastTrace < 140) return;
          lastTrace = now;
          var rect = den.getBoundingClientRect();
          var dot = document.createElement('div');
          dot.style.position = 'absolute';
          dot.style.left = (e.clientX - rect.left - 7) + 'px';
          dot.style.top = (e.clientY - rect.top - 7) + 'px';
          dot.style.width = '14px';
          dot.style.height = '14px';
          dot.style.opacity = '0.5';
          dot.style.pointerEvents = 'none';
          dot.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
          dot.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" style="fill:#F2A93B"><use href="#icon-paw" xlink:href="#icon-paw"/></svg>';
          den.appendChild(dot);
          requestAnimationFrame(function () {
            dot.style.opacity = '0';
            dot.style.transform = 'scale(0.4)';
          });
          setTimeout(function () { dot.remove(); }, 850);
        });
      }

    function getPostById(postId) {
      return feedPosts.find((p) => String(p.id) === String(postId));
    }

    function getPostSharePayload(postId) {
      const post = getPostById(postId);
      const text = post?.content ? String(post.content).trim() : "Check out this PawdCast post";
      const shortText = text.length > 140 ? `${text.slice(0, 137)}...` : text;
      const url = `${window.location.origin}${window.location.pathname}#pawdcast-${encodeURIComponent(String(postId))}`;
      const title = `${post?.author || "PawCircle"} on PawdCast`;
      return { post, title, text: shortText, url };
    }

    function copyPostLink(postId) {
      const payload = getPostSharePayload(postId);
      const write = navigator?.clipboard?.writeText
        ? navigator.clipboard.writeText(payload.url)
        : Promise.reject(new Error("Clipboard API unavailable"));

      write
        .then(() => showToast("Post link copied."))
        .catch(() => {
          const ok = window.prompt("Copy post link:", payload.url);
          if (ok !== null) showToast("Post link ready to copy.");
        });
    }

    async function sharePostNative(postId) {
      const payload = getPostSharePayload(postId);
      if (navigator.share) {
        try {
          await navigator.share({ title: payload.title, text: payload.text, url: payload.url });
        } catch (_) { }
      } else {
        copyPostLink(postId);
        return;
      }
    }

    let reactionAudioCtx = null;
    function getReactionAudioContext() {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return null;
        if (!reactionAudioCtx) reactionAudioCtx = new AudioCtx();
        return reactionAudioCtx;
      } catch (_) {
        return null;
      }
    }

    function playReactionSound(reactionKey) {
      const ctx = getReactionAudioContext();
      if (!ctx) return;

      const now = ctx.currentTime;
      const master = ctx.createGain();
      master.connect(ctx.destination);
      master.gain.setValueAtTime(0.0001, now);
      master.gain.exponentialRampToValueAtTime(0.12, now + 0.01);
      master.gain.exponentialRampToValueAtTime(0.0001, now + 0.42);

      const beep = (freq, start, duration, type = "sine", gainVal = 0.08) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, now + start);
        gain.gain.setValueAtTime(0.0001, now + start);
        gain.gain.exponentialRampToValueAtTime(gainVal, now + start + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + start + duration);
        osc.connect(gain);
        gain.connect(master);
        osc.start(now + start);
        osc.stop(now + start + duration + 0.03);
      };

      if (reactionKey === "bone" || reactionKey === "Liked") {
        beep(440, 0, 0.08); beep(554, 0.1, 0.12);
      } else if (reactionKey === "bark" || reactionKey === "Ashirwad given") {
        beep(150, 0, 0.1, "sawtooth"); beep(120, 0.1, 0.15, "sawtooth");
      } else if (reactionKey === "heart" || reactionKey === "Loved") {
        beep(523.25, 0, 0.1); beep(659.25, 0.1, 0.15); beep(783.99, 0.2, 0.2);
      } else {
        beep(330, 0, 0.08); beep(440, 0.1, 0.12);
      }
    }


    function getNormalizedPetInput(input = currentUserObj) {
      if (typeof input === "string") {
        return { petType: input.trim(), breedDetails: "" };
      }
      if (!input || typeof input !== "object") {
        return { petType: "", breedDetails: "" };
      }
      return {
        petType: String(
          input.pet_type || input.pet_type || input.socialProfile?.pet_type || "",
        ).trim(),
        breedDetails: String(
          input.socialProfile?.breedDetails ||
          input.mother_tongue ||
          input.breed_details ||
          input.breed ||
          "",
        ).trim(),
      };
    }

    function resolvePetThemeKey(input = currentUserObj) {
      const { petType, breedDetails } = getNormalizedPetInput(input);
      const profileText = `${petType} ${breedDetails}`.toLowerCase();

      if (/(horse|pony|mare|stallion|foal|equestrian|equine)/.test(profileText)) {
        return "horse";
      }
      if (
        petType.toLowerCase() === "bird" ||
        /(bird|parrot|cockatiel|budgie|canary|finch|macaw|lovebird)/.test(profileText)
      ) {
        return "bird";
      }
      if (
        petType.toLowerCase() === "fish" ||
        /(fish|guppy|goldfish|betta|koi|tetra|cichlid|aquatic)/.test(profileText)
      ) {
        return "fish";
      }
      if (
        petType.toLowerCase() === "reptile" ||
        /(reptile|snake|lizard|gecko|iguana|tortoise|turtle|python)/.test(profileText)
      ) {
        return "reptile";
      }
      if (
        petType.toLowerCase() === "rabbit" ||
        /(rabbit|bunny|hare)/.test(profileText)
      ) {
        return "rabbit";
      }
      if (
        petType.toLowerCase() === "small pets" ||
        /(hamster|guinea pig|ferret|chinchilla|gerbil|small pet)/.test(profileText)
      ) {
        return "small-pets";
      }
      if (
        petType.toLowerCase() === "cat" ||
        /(cat|kitten|persian|siamese|maine coon|ragdoll)/.test(profileText)
      ) {
        return "cat";
      }
      if (
        petType.toLowerCase() === "dog" ||
        /(dog|puppy|husky|labrador|beagle|pug|retriever|shepherd|indie mix|furry)/.test(profileText)
      ) {
        return "dog";
      }
      return "other";
    }

    function getPetTheme(input = currentUserObj) {
      const key = resolvePetThemeKey(input);
      const themes = {
        dog: {
          key: "dog",
          label: "Dog",
          landName: "Labrador Meadow",
          mascotName: "Golden Labrador",
          accent: "#ffab2e",
          pastelBgClass: "bg-sky-50 dark:bg-stone-950",
          signupGradient: "from-sky-100 via-yellow-50 to-lime-100",
          darkClass: "bg-sky-900",
          bannerGradient: "from-sky-400 via-amber-300 to-lime-300",
          emoji: "🐶",
          emblem: "🐾",
          greeting: "Ready to play",
          greetingEn: "Dog breed",
          highlight: {
            title: "Sunny Pup Spotlight",
            text: "Share park playdates, toy picks, cheerful walks, training wins, and wag-filled neighborhood moments.",
          },
          event: {
            month: "Jun",
            day: "22",
            title: "Sunny Pup Playday",
            place: "Meadow Circle Park",
            desc: "Meet dog parents for fetch games, cheerful walks, toy swaps, and playful introductions.",
          },
          sectionEmoji: { feed: "🎾", friends: "🐾", connections: "📍", groups: "🌳", live: "☀️", events: "🎉", guides: "🦴", match: "💞", memorial: "🌈" },
        },
        cat: {
          key: "cat",
          label: "Cat",
          accent: "#ff5ca8",
          pastelBgClass: "bg-pink-50 dark:bg-stone-950",
          signupGradient: "from-rose-100 via-pink-50 to-fuchsia-100",
          darkClass: "bg-pink-900",
          bannerGradient: "from-rose-500 via-pink-400 to-fuchsia-400",
          emoji: "🐱",
          emblem: "🐾",
          greeting: "Cozy and curious",
          greetingEn: "Cat breed",
          highlight: {
            title: "Whisker Spotlight",
            text: "Share enrichment ideas, favorite napping zones, and local rescue stories.",
          },
          event: {
            month: "Apr",
            day: "14",
            title: "Cat Parent Coffee Meetup",
            place: "Whiskers Cafe",
            desc: "Meet local cat parents, swap enrichment ideas, and connect with rescue volunteers.",
          },
          sectionEmoji: { feed: "🧶", friends: "🐾", connections: "📍", groups: "🏠", live: "🎥", events: "🎀", guides: "🐟", match: "💞", memorial: "🌈" },
        },
        bird: {
          key: "bird",
          label: "Bird",
          accent: "#1aa7ec",
          pastelBgClass: "bg-cyan-50 dark:bg-slate-950",
          signupGradient: "from-sky-100 via-cyan-50 to-teal-100",
          darkClass: "bg-sky-900",
          bannerGradient: "from-sky-500 via-cyan-400 to-teal-300",
          emoji: "🦜",
          emblem: "🪽",
          greeting: "Wings up",
          greetingEn: "Bird breed",
          highlight: {
            title: "Aviary Spotlight",
            text: "Compare enrichment perches, flight routines, and calm social introductions.",
          },
          event: {
            month: "Jul",
            day: "08",
            title: "Bird Keeper Perch Talk",
            place: "Skyline Aviary Club",
            desc: "Trade habitat tips, diet notes, and socialization routines with fellow keepers.",
          },
          sectionEmoji: { feed: "🪶", friends: "🪽", connections: "📍", groups: "🌿", live: "🎥", events: "🎉", guides: "🌤️", match: "💞", memorial: "🌈" },
        },
        rabbit: {
          key: "rabbit",
          label: "Rabbit",
          accent: "#7ecf6b",
          pastelBgClass: "bg-lime-50 dark:bg-stone-950",
          signupGradient: "from-lime-100 via-emerald-50 to-yellow-50",
          darkClass: "bg-lime-900",
          bannerGradient: "from-lime-400 via-emerald-400 to-amber-200",
          emoji: "🐰",
          emblem: "🥕",
          greeting: "Hop in",
          greetingEn: "Rabbit breed",
          highlight: {
            title: "Warren Spotlight",
            text: "Share bunny-safe spaces, bonding milestones, and forage-friendly routines.",
          },
          event: {
            month: "May",
            day: "11",
            title: "Bunny Bonding Circle",
            place: "Clover Breed Hall",
            desc: "Meet rabbit parents and talk bonding, enrichment tunnels, and grooming care.",
          },
          sectionEmoji: { feed: "🥕", friends: "🐰", connections: "📍", groups: "🌱", live: "🎥", events: "🎉", guides: "🥬", match: "💞", memorial: "🌈" },
        },
        fish: {
          key: "fish",
          label: "Fish",
          accent: "#14c8d8",
          pastelBgClass: "bg-sky-50 dark:bg-slate-950",
          signupGradient: "from-cyan-100 via-sky-50 to-blue-100",
          darkClass: "bg-cyan-900",
          bannerGradient: "from-cyan-400 via-sky-400 to-blue-500",
          emoji: "🐠",
          emblem: "🫧",
          greeting: "Dive in",
          greetingEn: "Aquatic breed",
          highlight: {
            title: "Reef Spotlight",
            text: "Compare tank setups, water-care routines, and aquascape inspiration.",
          },
          event: {
            month: "Aug",
            day: "03",
            title: "Aquascape Swap Session",
            place: "Blue Current Studio",
            desc: "Exchange aquascaping tips, tank maintenance routines, and fish-safe plant ideas.",
          },
          sectionEmoji: { feed: "🫧", friends: "🐠", connections: "📍", groups: "🌊", live: "🎥", events: "🎉", guides: "🪸", match: "💞", memorial: "🌈" },
        },
        reptile: {
          key: "reptile",
          label: "Reptile",
          accent: "#19bf84",
          pastelBgClass: "bg-emerald-50 dark:bg-slate-950",
          signupGradient: "from-emerald-100 via-teal-50 to-lime-100",
          darkClass: "bg-emerald-900",
          bannerGradient: "from-emerald-500 via-teal-400 to-lime-300",
          emoji: "🦎",
          emblem: "🌵",
          greeting: "Sun and scale",
          greetingEn: "Reptile breed",
          highlight: {
            title: "Terrarium Spotlight",
            text: "Share basking setups, humidity wins, and safe enclosure upgrades.",
          },
          event: {
            month: "Sep",
            day: "19",
            title: "Terrarium Build Meetup",
            place: "Habitat Lab",
            desc: "Compare enclosure layouts, heating setups, and enrichment ideas for exotic pets.",
          },
          sectionEmoji: { feed: "🌿", friends: "🦎", connections: "📍", groups: "🪵", live: "🎥", events: "🎉", guides: "☀️", match: "💞", memorial: "🌈" },
        },
        horse: {
          key: "horse",
          label: "Horse",
          accent: "#c17b3f",
          pastelBgClass: "bg-amber-50 dark:bg-stone-950",
          signupGradient: "from-amber-100 via-orange-50 to-yellow-50",
          darkClass: "bg-amber-900",
          bannerGradient: "from-amber-600 via-orange-400 to-yellow-300",
          emoji: "🐴",
          emblem: "🏇",
          greeting: "Stable and strong",
          greetingEn: "Horse breed",
          highlight: {
            title: "Stable Spotlight",
            text: "Connect around riding trails, grooming routines, and breed-focused meetups.",
          },
          event: {
            month: "Oct",
            day: "05",
            title: "Pony Pals Trail Day",
            place: "Saddle Ridge Grounds",
            desc: "Meet horse owners, trainers, and riders for a relaxed trail and care exchange.",
          },
          sectionEmoji: { feed: "🏇", friends: "🐴", connections: "📍", groups: "🏞️", live: "🎥", events: "🎉", guides: "🧲", match: "💞", memorial: "🌈" },
        },
        "small-pets": {
          key: "small-pets",
          label: "Small Pets",
          accent: "#9d6cff",
          pastelBgClass: "bg-violet-50 dark:bg-slate-950",
          signupGradient: "from-violet-100 via-fuchsia-50 to-pink-100",
          darkClass: "bg-violet-900",
          bannerGradient: "from-violet-500 via-fuchsia-400 to-pink-300",
          emoji: "🐹",
          emblem: "🌰",
          greeting: "Tiny but mighty",
          greetingEn: "Small pet breed",
          highlight: {
            title: "Pocket Pet Spotlight",
            text: "Swap habitat upgrades, gentle handling tips, and nutrition ideas for small companions.",
          },
          event: {
            month: "Nov",
            day: "16",
            title: "Pocket Pet Care Circle",
            place: "Cozy Habitat Hub",
            desc: "Share habitat hacks, enrichment toys, and rescue support for smaller companions.",
          },
          sectionEmoji: { feed: "🌰", friends: "🐹", connections: "📍", groups: "🏠", live: "🎥", events: "🎉", guides: "🧺", match: "💞", memorial: "🌈" },
        },
        other: {
          key: "other",
          label: "Pet",
          accent: "#ff6b6b",
          pastelBgClass: "bg-rose-50 dark:bg-slate-950",
          signupGradient: "from-rose-100 via-orange-50 to-amber-50",
          darkClass: "bg-rose-900",
          bannerGradient: "from-rose-500 via-orange-400 to-amber-300",
          emoji: "🐾",
          emblem: "✨",
          greeting: "Welcome",
          greetingEn: "Pet breed",
          highlight: {
            title: "Breed Spotlight",
            text: "Find nearby pet parents, exchange care ideas, and build trusted local circles.",
          },
          event: {
            month: "Jun",
            day: "22",
            title: "Neighborhood Pet Walk",
            place: "Central Park Loop",
            desc: "Join a friendly breed walk with quick intros for pet parents, walkers, and adopters.",
          },
          sectionEmoji: { feed: "✨", friends: "🐾", connections: "📍", groups: "🏡", live: "🎥", events: "🎉", guides: "📘", match: "💞", memorial: "🌈" },
        },
      };
      return themes[key] || themes.other;
    }

    // ── LOGIN PET TYPE SELECTOR & MASCOT ────────────────────────
    var loginFeedFeeds = null;
    var loginFeedRotateTimer = null;
    var loginFeedRenderFn = null;
    var loginFeedCurrentIdx = 0;

    function initLoginFeed() {
      var pane = document.getElementById('lp-left-pane');
      var card = document.getElementById('lp-feed-card');
      var onlineEl = document.getElementById('lp-online-count');
      if (!card) return;

      loginFeedFeeds = [
        {
          petTheme: 'Dog',
          icon: '🐕', grad: 'linear-gradient(135deg,#fffbeb 0%,#fef9c3 60%,#f0fdf4 100%)',
          chipText: 'Dog Breed · Active Now', chipCls: 'text-amber-700 border-amber-200',
          headline: 'Golden Hour Walk Guide for Summer 2026',
          excerpt: 'Vets recommend shifting walks to early morning and post-sunset as temperatures peak. Cool pavement prevents burned paws, while shorter shaded routes cut heatstroke risk. Always carry water and watch for excessive panting.',
          tips: [
            { icon: '💧', label: 'Fresh Water', sub: 'Every Hour', cls: 'bg-sky-50 border-sky-100 text-sky-700' },
            { icon: '🌅', label: 'Walk at', sub: 'Dawn / Dusk', cls: 'bg-amber-50 border-amber-100 text-amber-700' },
            { icon: '🐾', label: 'Paw Check', sub: 'After Walk', cls: 'bg-lime-50 border-lime-100 text-lime-700' },
          ],
          tags: ['🐕 Dogs', '☀️ Summer', '🌿 Wellness'],
          activity: ['🐕 Dog Walk at Central Park · Sunday 7 am', '🙌 28 new dog parents joined today', '🏥 Vet Q&amp;A thread trending'],
          online: 142,
        },
        {
          petTheme: 'Cat',
          icon: '🐈', grad: 'linear-gradient(135deg,#fdf4ff 0%,#ede9fe 60%,#f0f9ff 100%)',
          chipText: 'Cat Breed · Active Now', chipCls: 'text-purple-700 border-purple-200',
          headline: 'Keeping Indoor Cats Cool &amp; Stimulated',
          excerpt: 'Indoor cats need mental enrichment when hot weather keeps windows closed. Puzzle feeders, elevated perches, and window bird-feeders provide daily stimulation. Rotate toys weekly and schedule two play sessions daily to prevent boredom stress.',
          tips: [
            { icon: '🧩', label: 'Puzzle', sub: 'Feeders', cls: 'bg-purple-50 border-purple-100 text-purple-700' },
            { icon: '🪟', label: 'Window', sub: 'Bird Feeder', cls: 'bg-sky-50 border-sky-100 text-sky-700' },
            { icon: '🎾', label: 'Play 2×', sub: 'Daily', cls: 'bg-pink-50 border-pink-100 text-pink-700' },
          ],
          tags: ['🐈 Cats', '🏡 Indoor', '🌿 Enrichment'],
          activity: ['🐈 New group: Indoor Cat Club · 52 members', '📸 52 photos shared today', '🏥 Live Vet Q&amp;A · Tonight 8 pm'],
          online: 89,
        },
        {
          petTheme: 'Bird',
          icon: '🐦', grad: 'linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 60%,#ecfdf5 100%)',
          chipText: 'Bird Breed · Active Now', chipCls: 'text-sky-700 border-sky-200',
          headline: 'Socialising Your Parrot: A Step-by-Step Guide',
          excerpt: 'Building trust with a new parrot takes patience. Start with short 10-minute sessions near the cage without reaching in, offering treats through the bars. Gradually progress to step-up training with calm voices and positive reinforcement.',
          tips: [
            { icon: '🤝', label: 'Trust', sub: 'Sessions', cls: 'bg-sky-50 border-sky-100 text-sky-700' },
            { icon: '🍎', label: 'Treat', sub: 'Training', cls: 'bg-green-50 border-green-100 text-green-700' },
            { icon: '🔇', label: 'Calm', sub: 'Environment', cls: 'bg-indigo-50 border-indigo-100 text-indigo-700' },
          ],
          tags: ['🐦 Birds', '🦜 Parrots', '💬 Training'],
          activity: ['🐦 Parrot enrichment tips · 41 saves', '🎶 14 bird-call recordings shared', '📅 Bird Meet-Up · This Saturday'],
          online: 34,
        },
        {
          petTheme: 'Rabbit',
          icon: '🐇', grad: 'linear-gradient(135deg,#fff1f2 0%,#fce7f3 60%,#fff7ed 100%)',
          chipText: 'Rabbit Breed · Active Now', chipCls: 'text-pink-700 border-pink-200',
          headline: 'Rabbit-Proofing Your Home: The Essential Checklist',
          excerpt: 'Free-roaming rabbits are happier and healthier, but preparation is key. Cover electrical cords, block gaps behind furniture, and protect baseboards. Rabbits chew naturally — redirect them with safe hay bundles and willow toys.',
          tips: [
            { icon: '🔌', label: 'Cover', sub: 'All Cords', cls: 'bg-pink-50 border-pink-100 text-pink-700' },
            { icon: '🌾', label: 'Hay', sub: 'Bundles', cls: 'bg-amber-50 border-amber-100 text-amber-700' },
            { icon: '🚧', label: 'Block', sub: 'All Gaps', cls: 'bg-rose-50 border-rose-100 text-rose-700' },
          ],
          tags: ['🐇 Rabbits', '🏡 Safety', '🛡️ Proofing'],
          activity: ['🐇 New bonded-pair photo posted', '🐣 Rescue spotlight: 3 bunnies available', '📦 Hay-haul bulk buy thread'],
          online: 27,
        },
        {
          petTheme: 'Fish',
          icon: '🐠', grad: 'linear-gradient(135deg,#ecfeff 0%,#cffafe 60%,#f0fdf4 100%)',
          chipText: 'Aquatics Breed · Active Now', chipCls: 'text-cyan-700 border-cyan-200',
          headline: 'Summer Aquarium Care: Keeping Water Temperatures Safe',
          excerpt: 'Rising room temperatures push tank water above safe thresholds. Aim for 24–26 °C for tropical fish by running lights fewer hours, adding a fan across the water surface, or using frozen water bottles as a chiller. Test ammonia twice weekly.',
          tips: [
            { icon: '🌡️', label: '24–26°C', sub: 'Target Temp', cls: 'bg-cyan-50 border-cyan-100 text-cyan-700' },
            { icon: '💨', label: 'Fan Over', sub: 'Water Surface', cls: 'bg-sky-50 border-sky-100 text-sky-700' },
            { icon: '🧪', label: 'Test 2×', sub: 'Per Week', cls: 'bg-teal-50 border-teal-100 text-teal-700' },
          ],
          tags: ['🐠 Fish', '🌊 Aquascaping', '☀️ Summer'],
          activity: ['🐠 Aquascape build posted · 88 likes', '📊 Water test results thread', '🏆 Tank of the Month — vote now'],
          online: 18,
        },
        {
          petTheme: 'Reptile',
          icon: '🦎', grad: 'linear-gradient(135deg,#f7fee7 0%,#ecfccb 60%,#fefce8 100%)',
          chipText: 'Reptile Breed · Active Now', chipCls: 'text-lime-700 border-lime-200',
          headline: 'UVB Lighting &amp; Basking Zones: Getting It Right',
          excerpt: 'Proper UVB exposure is critical for bone health in diurnal reptiles. Replace UVB bulbs every 12 months even if they still emit visible light — output degrades invisibly. Maintain a clear gradient: 35–38 °C hot side, 24–27 °C cool side.',
          tips: [
            { icon: '☀️', label: 'Replace UVB', sub: 'Every 12 mo', cls: 'bg-lime-50 border-lime-100 text-lime-700' },
            { icon: '🌡️', label: 'Basking', sub: '35–38 °C', cls: 'bg-yellow-50 border-yellow-100 text-yellow-700' },
            { icon: '❄️', label: 'Cool Side', sub: '24–27 °C', cls: 'bg-sky-50 border-sky-100 text-sky-700' },
          ],
          tags: ['🦎 Reptiles', '💡 UVB', '🐍 Husbandry'],
          activity: ['🦎 Bioactive enclosure photos posted', '📷 Shed skin gallery · 9 new shots', '🩺 Exotic vet check-up guide posted'],
          online: 22,
        },
      ];

      loginFeedCurrentIdx = Math.floor(Math.random() * loginFeedFeeds.length);

      loginFeedRenderFn = function (forceTheme) {
        if (forceTheme) {
          var found = loginFeedFeeds.findIndex(function (f) { return f.petTheme === forceTheme; });
          if (found >= 0) loginFeedCurrentIdx = found;
        }

        var f = loginFeedFeeds[loginFeedCurrentIdx];
        var theme = getPetTheme(f.petTheme);
        if (onlineEl) onlineEl.textContent = (f.online + Math.floor(Math.random() * 14) - 7) + ' online';

        var tipsHtml = f.tips.map(function (t) {
          return '<div class="rounded-xl border px-2 py-2 text-center min-h-[78px] flex flex-col items-center justify-center ' + t.cls + '">' +
            '<div class="text-sm mb-0.5">' + t.icon + '</div>' +
            '<div class="text-[11px] font-semibold leading-tight">' + t.label + '<br>' + t.sub + '</div></div>';
        }).join('');

        var tagsHtml = f.tags.map(function (tag) {
          return '<span class="px-2 py-0.5 rounded-full bg-white border border-gray-200/70 text-xs font-medium text-gray-600">' + tag + '</span>';
        }).join('');

        var galleryThemes = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Fish', 'Reptile']
          .filter(function (themeName) { return themeName !== f.petTheme; });
        var breedGalleryHtml = galleryThemes.concat(galleryThemes).map(function (themeName) {
          var theme = getPetTheme(themeName);
          var art = PET_REAL_PHOTOS[themeName] || getPetIllustrationDataUrl(theme, 'badge');
          return '<div class="pet-breed-strip-item pet-breed-strip-item--login rounded-xl border border-white/85 bg-white/76 p-1.5 shadow-sm">' +
            '<div class="pet-thumb rounded-lg" style="background-image:linear-gradient(135deg, color-mix(in srgb, ' + theme.accent + ' 18%, white), transparent 70%), url(\'' + art + '\');background-size:cover;background-position:center;"></div>' +
            '<div class="mt-1 text-[10px] font-bold text-gray-700 text-center leading-tight">' + theme.label + '</div>' +
            '</div>';
        }).join('');

        card.innerHTML =
          '<div class="h-full flex flex-col">' +
          '<div class="flex items-center justify-between mb-1.5">' +
          '<span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-0.5 rounded-full bg-white border uppercase tracking-wide ' + f.chipCls + '">' +
          f.icon + ' ' + f.chipText +
          '</span>' +
          '<span class="text-xs text-gray-400">5 min read</span>' +
          '</div>' +
          '<h3 class="text-[15px] font-bold text-gray-800 leading-snug mb-1" style="font-family:\'DM Serif Display\';">' + f.headline + '</h3>' +
          '<p class="text-[13px] text-gray-600 leading-relaxed mb-2" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">' + f.excerpt + '</p>' +
          '<div class="grid grid-cols-3 gap-2 mb-2 auto-rows-fr">' + tipsHtml + '</div>' +
          '<div class="mt-auto pt-2 border-t border-white/80">' +
          '<div class="text-[10px] font-black uppercase tracking-[0.16em] text-gray-500 mb-1.5">Pet Breeds Near You</div>' +
          '<div class="pet-breed-strip rounded-xl">' +
          '<div class="pet-breed-strip-track">' + breedGalleryHtml + '</div>' +
          '</div>' +
          '</div>' +
          '<div class="mt-2 flex items-center gap-2 flex-wrap">' + tagsHtml + '</div>' +
          '</div>';

        card.style.opacity = '0';
        card.style.transform = 'translateY(8px)';
        requestAnimationFrame(function () {
          card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        });

        if (!forceTheme) {
          // Sync accent color
          var rightPane = document.getElementById('lp-right-pane');
          var loginView = document.getElementById('view-public-login');
          if (theme && loginView) {
            loginView.style.setProperty('--login-accent', theme.accent);
          }
          if (theme && rightPane) {
            rightPane.style.setProperty('--login-accent', theme.accent);
          }
          
          // Sync dropdown selection
          var loginPetTypeSelect = document.getElementById('login-pet-type');
          if (loginPetTypeSelect) {
            loginPetTypeSelect.value = f.petTheme;
          }
          
          loginFeedCurrentIdx = (loginFeedCurrentIdx + 1) % loginFeedFeeds.length;
        }
      }

      function start() {
        clearInterval(loginFeedRotateTimer);
        loginFeedRenderFn();
        loginFeedRotateTimer = setInterval(loginFeedRenderFn, 7000);
      }

      start();

      // Re-trigger each time the login view becomes active (after logout or back-nav)
      var loginView = document.getElementById('view-public-login');
      if (loginView) {
        new MutationObserver(function () {
          if (loginView.classList.contains('active')) {
            if (document.getElementById('login-pet-type').value) {
              // Keep stopped
            } else {
              start();
            }
          }
        }).observe(loginView, { attributes: true, attributeFilter: ['class'] });
      }
    }

    function handleLoginPetTypeChange(type) {
      var rightPane = document.getElementById('lp-right-pane');
      var loginView = document.getElementById('view-public-login');
      if (!type) {
        if (loginFeedRotateTimer) clearInterval(loginFeedRotateTimer);
        loginFeedRotateTimer = setInterval(loginFeedRenderFn, 7000);
        if (loginView) loginView.style.setProperty('--login-accent', '#f97316'); // Default brand
        if (rightPane) rightPane.style.setProperty('--login-accent', '#f97316'); // Default brand
        return;
      }

      // Stop rotation
      if (loginFeedRotateTimer) clearInterval(loginFeedRotateTimer);

      // Render specific theme
      if (loginFeedRenderFn) loginFeedRenderFn(type);

      // Update accent color
      var theme = getPetTheme(type);
      if (loginView && theme) {
        loginView.style.setProperty('--login-accent', theme.accent);
      }
      if (rightPane && theme) {
        rightPane.style.setProperty('--login-accent', theme.accent);
      }
    }



    // ── FEED MEDIA LOGIC ──────────────────────────────────────
    function openFeedMediaPicker() {
      var input = document.getElementById("feed-media-input");
      if (input) input.click();
    }

    function clearFeedMediaSelection() {
      var input = document.getElementById("feed-media-input");
      var previewContainer = document.getElementById("feed-media-preview");
      if (input) input.value = "";
      if (previewContainer) {
        previewContainer.classList.add("hidden");
        previewContainer.innerHTML = "";
      }
    }

    function handleFeedMediaSelection(event) {
      var file = event.target.files[0];
      if (!file) return;
      var previewContainer = document.getElementById("feed-media-preview");
      if (!previewContainer) return;

      var reader = new FileReader();
      reader.onload = function (e) {
        previewContainer.classList.remove("hidden");
        if (file.type.startsWith('video/')) {
          previewContainer.innerHTML = `
                    <div class="relative inline-block mt-2">
                        <video src="${e.target.result}" class="max-h-48 rounded-lg" controls></video>
                        <button onclick="clearFeedMediaSelection()" class="absolute -top-2 -right-2 bg-gray-900 text-white rounded-full p-1 shadow-md hover:bg-gray-800"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>`;
        } else {
          previewContainer.innerHTML = `
                    <div class="relative inline-block mt-2">
                        <img src="${e.target.result}" class="max-h-48 rounded-lg object-contain" />
                        <button onclick="clearFeedMediaSelection()" class="absolute -top-2 -right-2 bg-gray-900 text-white rounded-full p-1 shadow-md hover:bg-gray-800"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>`;
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
      };
      reader.readAsDataURL(file);
    }

    // Patch createPost to include media
    var originalCreatePost = window.createPost;
    if (originalCreatePost) {
      window.createPost = function (textId, hashId) {
        var mediaInput = document.getElementById("feed-media-input");
        var contentElem = document.getElementById(textId);
        if (!contentElem) return;

        var content = contentElem.value.trim();
        if (!content && (!mediaInput || !mediaInput.files[0])) return;

        if (mediaInput && mediaInput.files[0]) {
          var reader = new FileReader();
          reader.onload = function (e) {
            var newPost = {
              id: Date.now(),
              user_id: currentUserObj.id,
              author: currentUserObj.name,
              profilePhoto: currentUserObj.profilePhoto,
              content: content,
              media_url: e.target.result,
              time: "Just now",
              likes: 0,
              comments: 0
            };
            feedPosts.unshift(newPost);
            clearFeedMediaSelection();
            contentElem.value = "";
            renderFeed();
          };
          reader.readAsDataURL(mediaInput.files[0]);
        } else {
          originalCreatePost(textId, hashId);
        }
      };
    }

    // ── THREAD MANAGEMENT / POST DETAIL VIEW ────────────────────
    function handleFeedPostCardClick(event, postId) {
      if (event.defaultPrevented) return;
      const interactive = event.target.closest("button, a, input, textarea, select, label, video, [data-no-post-open], .post-menu, .comment-menu");
      if (interactive) return;
      openPostDetail(postId);
    }

    function closePostDetail() {
      switchSocialTab("feed", { skipScroll: true });
      try {
        const url = new URL(window.location.href);
        if (url.searchParams.has("post")) {
          url.searchParams.delete("post");
          window.history.replaceState({}, "", url.toString());
        }
      } catch (e) { }
    }

    function openPostDetail(postId, options = {}) {
      const post = feedPosts.find((p) => String(p.id) === String(postId));
      if (!post) {
        if (typeof showToast !== 'undefined') showToast("Post is no longer available.");
        return;
      }
      switchSocialTab("post-detail", { remember: false, skipScroll: true });
      renderPostDetail(postId);
      if (options.updateUrl !== false) {
        try {
          const url = new URL(window.location.href);
          url.searchParams.set("post", postId);
          url.hash = "feed";
          window.history.replaceState({}, "", url.toString());
        } catch (e) { }
      }
      window.scrollTo({ top: 0, behavior: options.instant ? "auto" : "smooth" });
    }

    function renderPostDetail(postId) {
      const container = document.getElementById("post-detail-view");
      const post = feedPosts.find((p) => String(p.id) === String(postId));
      if (!container || !post) return;
      const safePostId = String(post.id).replace(/'/g, "\\'");
      const safeAuthor = escapeHtml(post.author).replace(/'/g, "\\'");
      const safeProfilePhoto = post.profilePhoto ? escapeHtml(post.profilePhoto).replace(/'/g, "\\'") : "";
      const avatarHtml = post.profilePhoto
        ? `<img src="${escapeHtml(post.profilePhoto)}" onclick="openUserProfile('${safeAuthor}', 'Breed Member', '${String(post.user_id || '').replace(/'/g, "\\'")}', '${safeProfilePhoto}')" loading="lazy" decoding="async" class="w-11 h-11 rounded-full object-cover cursor-pointer hover:ring-2 hover:ring-brand-400 transition-all" alt="">`
        : `<div onclick="openUserProfile('${safeAuthor}', 'Breed Member', '${String(post.user_id || '').replace(/'/g, "\\'")}')" class="w-11 h-11 ${post.avatarClass || 'bg-gray-200 text-gray-700'} rounded-full flex items-center justify-center font-bold cursor-pointer hover:ring-2 hover:ring-brand-400 transition-all">${post.initials || 'U'}</div>`;

      const mediaHtml = post.media_url ? `<img src="${escapeHtml(post.media_url)}" class="mt-4 rounded-xl max-h-[500px] w-full object-contain bg-gray-50 border border-gray-100" />` : "";
      const descriptionText = post.description || post.content || "";
      const descriptionHtml = descriptionText ? `<p class="text-base leading-7 text-gray-800 whitespace-pre-wrap break-words mt-2">${escapeHtml(descriptionText)}</p>` : "";

      container.innerHTML = `
        <div class="mx-auto max-w-2xl space-y-4 pt-4">
          <button type="button" onclick="closePostDetail()" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-base font-bold shadow-md text-gray-700 hover:border-brand-200 hover:text-brand-600 transition-colors shadow-sm mb-2">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to feed
          </button>
          <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-md">
            <div class="flex items-start justify-between gap-4 border-b border-gray-50 p-4 sm:p-5">
              <div class="flex min-w-0 items-start gap-3">
                ${avatarHtml}
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-1.5 text-base font-bold text-gray-900">
                    <span class="cursor-pointer hover:underline" onclick="openUserProfile('${safeAuthor}')">${escapeHtml(post.author)}</span>
                  </div>
                  <div class="mt-1 text-xs text-gray-500">${escapeHtml(post.time || 'Just now')}</div>
                </div>
              </div>
            </div>
            <div class="space-y-4 p-4 sm:p-5">
              ${descriptionHtml}
              ${mediaHtml}
            </div>
          </article>
        </div>`;
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    initLoginFeed();

  