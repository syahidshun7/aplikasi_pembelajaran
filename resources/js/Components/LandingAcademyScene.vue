<template>
    <figure class="academy-scene">
        <img
            src="/images/landing/academy-study-scene-modern.png"
            alt="Tiga murid belajar di sekolah sihir modern; seorang murid menggunakan laptop, dengan ponsel mati di meja."
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
            <ellipse class="candle-light" cx="654" cy="731" rx="76" ry="68" fill="url(#academy-candle-light)"/>
            <g class="flame" shape-rendering="crispEdges">
                <path fill="#f49137" d="M650 692h-5v-11h5v-8h5v-10h5v8h5v9h5v11h-5v4h-15z"/>
                <path fill="#ffe18a" d="M653 693h-4v-9h4v-7h5v-7h4v11h4v9h-4v4h-9z"/>
                <path fill="#fff4cc" d="M655 693v-9h5v-4h3v13z"/>
            </g>
            <g class="flame flame--alternate" shape-rendering="crispEdges">
                <path fill="#f49137" d="M648 693h-4v-10h4v-8h6v-8h6v7h6v6h4v12h-6v5h-16z"/>
                <path fill="#ffe18a" d="M650 693v-9h5v-7h5v5h5v11h-5v3h-10z"/>
                <path fill="#fff4cc" d="M654 693v-8h6v-4h3v12z"/>
            </g>
            <path class="steam" d="M840 708c-18-12 11-20 2-33s-8-14 0-25" fill="none" stroke="#fff1db" stroke-width="5" stroke-linecap="round"/>
            <path class="steam steam--second" d="M857 708c12-11-11-18-4-30s8-14 2-24" fill="none" stroke="#fff1db" stroke-width="4" stroke-linecap="round"/>
            <g shape-rendering="crispEdges">
                <path class="blink blink--reader" fill="#f4aa83" d="M351 522h17v20h-17zM395 520h17v22h-17z"/>
                <path class="blink blink--listener" fill="#f4aa83" d="M651 521h17v20h-17zM694 520h17v21h-17z"/>
                <path class="blink blink--writer" fill="#f4aa83" d="M1018 518h18v22h-18zM1072 516h18v22h-18z"/>
            </g>
            <svg class="typing-hands" x="252" y="674" width="112" height="70" viewBox="252 674 112 70">
                <clipPath id="academy-typing">
                    <path d="M263 675h16v7h15v6h15v8h13v12h12v8h10v13h-43v-7h-13v-8h-11v-9h-9v-11h-7v-9h-8z"/>
                </clipPath>
                <image href="/images/landing/academy-study-scene-modern.png" width="1448" height="1086" clip-path="url(#academy-typing)"/>
            </svg>
            <svg class="writing-hand" x="964" y="645" width="118" height="92" viewBox="964 645 118 92">
                <clipPath id="academy-writing-hand">
                    <path d="M971 648h20v8h14v8h17v10h13v9h11v15h13v30h-53v-8h-12v-10h-11v-12h-7v-17h-8v-17h-7z"/>
                </clipPath>
                <image href="/images/landing/academy-study-scene-modern.png" width="1448" height="1086" clip-path="url(#academy-writing-hand)"/>
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
.steam { animation: rising-steam 4.8s ease-in-out infinite; }
.steam--second { animation-delay: -2.4s; }
.blink { opacity: 0; animation: blink 6.6s steps(1, end) infinite; }
.blink--listener { animation-delay: -2.2s; }
.blink--writer { animation-delay: -4.4s; }
.typing-hands { transform-origin: 50% 50%; animation: typing 2.8s ease-in-out infinite; }
.writing-hand { transform-origin: 50% 75%; animation: writing 5.2s ease-in-out infinite; }

@keyframes flame-frame { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
@keyframes warm-light { from { opacity: .22; } to { opacity: .62; } }
@keyframes rising-steam {
    0% { opacity: 0; transform: translate(0, 10px); }
    32% { opacity: .58; }
    65% { opacity: .25; transform: translate(6px, -14px); }
    100% { opacity: 0; transform: translate(-5px, -33px); }
}
@keyframes blink { 0%, 96%, 100% { opacity: 0; } 94% { opacity: 1; } }
@keyframes writing {
    0%, 54%, 100% { transform: translate(0, 0); }
    62%, 72% { transform: translate(1px, -1px); }
    78% { transform: translate(0, 0); }
}
@keyframes typing {
    0%, 10%, 19%, 28%, 53%, 63%, 72%, 81%, 100% { transform: translate(0, 0); }
    14%, 24%, 67%, 76% { transform: translate(1px, -1px); }
}

@media (prefers-reduced-motion: reduce) {
    .academy-scene__motion * { animation: none; }
    .flame--alternate, .steam { display: none; }
    .candle-light { opacity: .35; }
    .typing-hands, .writing-hand { animation: none; }
}
</style>
