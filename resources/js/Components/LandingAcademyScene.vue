<template>
    <figure class="academy-scene">
        <img
            src="/images/landing/academy-study-scene.webp"
            alt="Tiga murid belajar bersama di akademi sihir dengan buku, perkamen, lilin, dan kopi."
            width="1448"
            height="1086"
            fetchpriority="high"
        >
        <svg class="academy-scene__motion" viewBox="0 0 1448 1086" aria-hidden="true">
            <defs>
                <radialGradient id="academy-candle-light">
                    <stop stop-color="#ffbf59" stop-opacity=".5"/>
                    <stop offset="1" stop-color="#ffbf59" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <ellipse class="candle-light" cx="703" cy="710" rx="76" ry="68" fill="url(#academy-candle-light)"/>
            <g class="flame" shape-rendering="crispEdges">
                <path fill="#f49137" d="M697 709h-5v-12h5v-9h5v-10h5v9h5v12h5v10h-5v5h-15z"/>
                <path fill="#ffe18a" d="M700 710h-4v-9h4v-7h5v-7h4v12h4v10h-4v4h-9z"/>
                <path fill="#fff4cc" d="M702 711v-10h5v-4h3v14z"/>
            </g>
            <g class="flame flame--alternate" shape-rendering="crispEdges">
                <path fill="#f49137" d="M695 710h-4v-10h4v-9h6v-9h6v8h6v7h4v13h-6v5h-16z"/>
                <path fill="#ffe18a" d="M697 710v-10h5v-8h5v5h5v13h-5v3h-10z"/>
                <path fill="#fff4cc" d="M701 710v-9h6v-4h3v13z"/>
            </g>
            <path class="steam" d="M866 752c-19-11 12-19 2-32s-9-14 0-24" fill="none" stroke="#fff1db" stroke-width="5" stroke-linecap="round"/>
            <path class="steam steam--second" d="M881 751c12-11-12-17-4-29s9-14 2-23" fill="none" stroke="#fff1db" stroke-width="4" stroke-linecap="round"/>
            <ellipse class="book-light" cx="401" cy="729" rx="181" ry="70" fill="url(#academy-candle-light)"/>
            <g shape-rendering="crispEdges">
                <path class="blink blink--reader" fill="#f5ba79" d="M361 545h13v24h-13zM402 543h14v24h-14z"/>
                <path class="blink blink--listener" fill="#f5ba79" d="M679 545h12v23h-12zM716 548h13v24h-13z"/>
                <path class="blink blink--writer" fill="#f5ba79" d="M1064 552h14v25h-14zM1107 552h14v25h-14z"/>
            </g>
            <path class="turn-page" shape-rendering="crispEdges" fill="#ffe4a0" stroke="#ca914c" stroke-width="5"
                d="M398 780v-94h8v-12h12v-9h18v-8h29v-6h31v6h-16v12h-16v13h-13v16h-13v17h-11v18h-12v22h-9v25z"/>
            <svg class="writing-hand" x="997" y="608" width="82" height="135" viewBox="997 608 82 135">
                <clipPath id="academy-quill">
                    <path d="M1000 609h17v8h10v11h11v16h10v17h10v21h6v17h9v34h-44v-10h-9v-23h9v-9h16v-10h-13v-12h-11v-14h-8v-17h-9v-15h-4z"/>
                </clipPath>
                <image href="/images/landing/academy-study-scene.webp" width="1448" height="1086" clip-path="url(#academy-quill)"/>
            </svg>
        </svg>
    </figure>
</template>

<style scoped>
.academy-scene { position: relative; margin: 0; }
.academy-scene img { display: block; width: 100%; height: auto; image-rendering: pixelated; }
.academy-scene__motion { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.academy-scene__motion image { image-rendering: pixelated; }
.flame { animation: flame-frame 1.4s steps(1, end) infinite; }
.flame--alternate { animation-delay: -0.7s; }
.candle-light { animation: warm-light 2.8s ease-in-out infinite alternate; }
.book-light { animation: warm-light 5s ease-in-out infinite alternate; }
.steam { animation: rising-steam 4.8s ease-in-out infinite; }
.steam--second { animation-delay: -2.4s; }
.blink { opacity: 0; animation: blink 6.6s steps(1, end) infinite; }
.blink--listener { animation-delay: -2.2s; }
.blink--writer { animation-delay: -4.4s; }
.turn-page { opacity: 0; transform-origin: 398px 780px; animation: turn-page 12s steps(5, end) infinite; }
.writing-hand { transform-origin: 1048px 718px; animation: writing 6s steps(1, end) infinite; }

@keyframes flame-frame { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
@keyframes warm-light { from { opacity: .22; } to { opacity: .62; } }
@keyframes rising-steam {
    0% { opacity: 0; transform: translate(0, 10px); }
    32% { opacity: .58; }
    65% { opacity: .25; transform: translate(6px, -14px); }
    100% { opacity: 0; transform: translate(-5px, -33px); }
}
@keyframes blink { 0%, 96%, 100% { opacity: 0; } 94% { opacity: 1; } }
@keyframes turn-page {
    0%, 89% { opacity: 0; transform: scaleX(.75); }
    90% { opacity: 1; transform: scaleX(.75); }
    96% { opacity: 1; transform: scaleX(-.75); }
    97%, 100% { opacity: 0; transform: scaleX(-.75); }
}
@keyframes writing {
    0%, 25%, 39%, 53%, 100% { transform: none; }
    18%, 32%, 46% { transform: translate(3px, 2px) rotate(2deg); }
}

@media (prefers-reduced-motion: reduce) {
    .academy-scene__motion * { animation: none; }
    .flame--alternate, .steam { display: none; }
    .candle-light, .book-light { opacity: .35; }
}
</style>
