<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    stages: {
        type: Array,
        required: true
    },
    clearedStageUuids: {
        type: Array,
        default: () => []
    },
    isActive: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['interact-terminal', 'reach-exit', 'update-integrity']);

const canvasRef = ref(null);
let ctx = null;
let animationFrameId = null;

// Tile System
const tileSize = 48; // Scaled up slightly
let mapGrid = [];
let mapWidth = 60;
let mapHeight = 60;
let isLabyrinth = false;
let floatingTexts = [];

const TILE_GROUND = 0;
const TILE_OBSTACLE_TREE = 1;
const TILE_WATER = 2;
const TILE_NPC = 3;
const TILE_PORTAL_LOCKED = 4;
const TILE_PORTAL_OPEN = 5;
const TILE_LOCKED_CHEST = 6;
const TILE_OPEN_CHEST = 7;
const TILE_SPIKE = 8;

const loadImage = (src) => {
    const img = new Image();
    img.src = src;
    return img;
};

// SVG Fallback for Water
const svgWater = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <rect width="100" height="100" fill="#3498db"/>
  <path d="M 0 50 Q 25 30 50 50 T 100 50" stroke="#2980b9" stroke-width="5" fill="none"/>
  <path d="M 0 70 Q 25 50 50 70 T 100 70" stroke="#2980b9" stroke-width="5" fill="none"/>
</svg>`;
const svgToImg = (str) => {
    const img = new Image();
    img.src = 'data:image/svg+xml;base64,' + btoa(str);
    return img;
};

// Asset Loader
const assets = {
    player: loadImage('/assets/rpg_codingame/human.png'),
    water: svgToImg(svgWater),
    enemy: loadImage('/assets/rpg_codingame/enemy.png'),
    chest: loadImage('/assets/rpg_codingame/chest.png'),
    house: loadImage('/assets/rpg_codingame/house.png'),
    wall: loadImage('/assets/rpg_codingame/wall.png'),
    grass: loadImage('/assets/rpg_codingame/grass.jpg')
};

// Audio System
const audio = {
    bgm: new Audio('/assets/rpg_codingame/bgm.ogg'),
    slash: new Audio('/assets/rpg_codingame/slash.ogg'),
    hit: new Audio('/assets/rpg_codingame/hit.ogg')
};
audio.bgm.loop = true;
audio.bgm.volume = 0.4;
audio.slash.volume = 0.7;
audio.hit.volume = 0.6;

const THEMES = [
    { name: 'Forest', ground: '#a2d149', dot: '#71aa34', treeImg: assets.wall },
    { name: 'Desert', ground: '#e1b12c', dot: '#c89211', treeImg: assets.wall },
    { name: 'Dungeon', ground: '#636e72', dot: '#2d3436', treeImg: assets.wall },
    { name: 'Snow', ground: '#dfe6e9', dot: '#b2bec3', treeImg: assets.wall }
];
let currentTheme = THEMES[0];
let isBlind = false;

let assetsLoaded = 0;
const totalAssets = 7;

Object.values(assets).forEach(img => {
    img.onload = () => { assetsLoaded++; };
    img.onerror = () => { assetsLoaded++; }; // Avoid hanging
});

// Game State
let currentMapStageIndex = 0;
let activeStageUuid = null;
let npcPos = { x: 0, y: 0 };
let portalPos = { x: 0, y: 0 };

// Player Object
const player = {
    x: 2,
    y: 2,
    px: 2 * tileSize,
    py: 2 * tileSize,
    speed: 4,
    size: 24,
    direction: 0, // 0: down, 1: up, 2: left, 3: right
    frame: 0,
    isMoving: false,
    isAttacking: false,
    attackFrame: 0,
    health: 10,
    maxHealth: 10,
    level: 1,
    exp: 0,
    keys: 0,
    trapCooldown: 0,
    isDead: false,
    buffSpeedTimer: 0,
    buffOneHitTimer: 0,
    vx: 0,
    vy: 0
};
let screenShake = 0;
let hitStopTimer = 0;
let walkAnimTimer = 0;
let enemyAnimTimer = 0;
let lastFrameTs = 0;
let enemyThinkTimer = 0;
const sparks = [];
let visibilityHandler = null;

let mapCacheCanvas = null;
let mapCacheCtx = null;
let mapCacheDirty = true;

let drops = [];

// Enemies (Slimes/Monsters)
let enemies = [];
const createEnemy = (x, y) => {
    return {
        px: x * tileSize,
        py: y * tileSize,
        hp: 3,
        speed: 1.5,
        direction: 0,
        frame: 0,
        dead: false,
        cooldown: 0
    };
};

const keys = { w: false, a: false, s: false, d: false, space: false };
const interactCooldown = ref(0);
let timeAnim = 0;
const isTouchDevice = ref(false);
const actionPrompt = ref('Attack');
const objectiveLabel = ref('Clear enemies and reach terminal');
const showHint = ref(false);
let hintToastFrames = 0;
let stuckFrames = 0;
let lastProgressSnapshot = '';
const joystick = {
    active: false,
    pointerId: null,
    baseX: 0,
    baseY: 0,
    knobX: 0,
    knobY: 0,
};

const joystickVisible = ref(false);
const joystickBaseStyle = ref({ left: '0px', top: '0px' });
const joystickKnobStyle = ref({ left: '0px', top: '0px' });
const moveDeadzone = 14;
const moveClamp = 44;

const randInt = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
const BASE_FRAME_MS = 1000 / 60;

const createMapCache = () => {
    if (typeof document === 'undefined') return;
    mapCacheCanvas = document.createElement('canvas');
    mapCacheCanvas.width = mapWidth * tileSize;
    mapCacheCanvas.height = mapHeight * tileSize;
    mapCacheCtx = mapCacheCanvas.getContext('2d');
    mapCacheDirty = true;
};

const drawStaticTile = (targetCtx, x, y, tile) => {
    const px = x * tileSize;
    const py = y * tileSize;
    if (currentTheme.name === 'Forest' || currentTheme.name === 'Snow') {
        targetCtx.drawImage(assets.grass, 0, 0, 128, 128, px, py, tileSize, tileSize);
    } else {
        targetCtx.fillStyle = currentTheme.ground;
        targetCtx.fillRect(px, py, tileSize, tileSize);
        targetCtx.fillStyle = currentTheme.dot;
        if ((x * 13 + y * 7) % 3 === 0) {
            targetCtx.fillRect(px + 8, py + 8, 4, 4);
            targetCtx.fillRect(px + tileSize - 12, py + tileSize - 12, 4, 4);
        }
    }

    if (tile === TILE_OBSTACLE_TREE) {
        targetCtx.drawImage(currentTheme.treeImg, px, py - tileSize / 2, tileSize, tileSize * 1.5);
    } else if (tile === TILE_NPC) {
        targetCtx.drawImage(assets.chest, px, py, tileSize, tileSize);
    } else if (tile === TILE_PORTAL_LOCKED) {
        targetCtx.fillStyle = '#34495e';
        targetCtx.fillRect(px, py, tileSize, tileSize);
    } else if (tile === TILE_PORTAL_OPEN) {
        targetCtx.fillStyle = '#2c3e50';
        targetCtx.fillRect(px, py, tileSize, tileSize);
        targetCtx.fillStyle = '#3498db';
        targetCtx.fillRect(px + 10, py + 10, tileSize - 20, tileSize - 20);
    } else if (tile === TILE_LOCKED_CHEST) {
        targetCtx.fillStyle = '#7f8c8d';
        targetCtx.fillRect(px + 8, py + 8, tileSize - 16, tileSize - 16);
        targetCtx.fillStyle = '#2c3e50';
        targetCtx.fillRect(px + tileSize / 2 - 4, py + tileSize / 2 - 4, 8, 8);
    } else if (tile === TILE_OPEN_CHEST) {
        targetCtx.fillStyle = '#95a5a6';
        targetCtx.fillRect(px + 8, py + 8, tileSize - 16, tileSize - 16);
        targetCtx.fillStyle = '#bdc3c7';
        targetCtx.fillRect(px + 8, py + 8, tileSize - 16, 8);
    } else if (tile === TILE_WATER) {
        targetCtx.drawImage(assets.water, px, py, tileSize, tileSize);
    }

    if (tile === 10 && mapGrid[y - 1]?.[x] !== 10 && mapGrid[y][x - 1] !== 10) {
        targetCtx.drawImage(assets.house, px, py - tileSize, tileSize * 3, tileSize * 3);
    }
};

const rebuildMapCache = () => {
    if (!mapCacheCtx || assetsLoaded < totalAssets) {
        mapCacheDirty = true;
        return;
    }
    mapCacheCtx.clearRect(0, 0, mapCacheCanvas.width, mapCacheCanvas.height);
    for (let y = 0; y < mapHeight; y++) {
        for (let x = 0; x < mapWidth; x++) {
            drawStaticTile(mapCacheCtx, x, y, mapGrid[y][x]);
        }
    }
    mapCacheDirty = false;
};

const setupMapForCurrentStage = () => {
    if (currentMapStageIndex >= props.stages.length) {
        currentMapStageIndex = props.stages.length - 1;
    }
    activeStageUuid = props.stages[currentMapStageIndex]?.uuid;
    
    // Pick random theme and blindness
    currentTheme = THEMES[Math.floor(Math.random() * THEMES.length)];
    isBlind = !isTouchDevice.value && Math.random() < 0.2; // desktop only, lower chance
    
    // Labyrinth check (40% chance)
    isLabyrinth = Math.random() < 0.40;
    
    if (isLabyrinth) {
        mapWidth = 30;
        mapHeight = 30;
        generateLabyrinthMap();
    } else {
        mapWidth = 60;
        mapHeight = 60;
        generateMap();
    }
    
    // Reset Player
    player.x = 2;
    player.y = 2;
    player.px = player.x * tileSize + tileSize / 2;
    player.py = player.y * tileSize + tileSize / 2;
    player.direction = 0;
    player.isDead = false;
    player.buffSpeedTimer = 0;
    player.buffOneHitTimer = 0;
    player.health = player.maxHealth;
    player.vx = 0;
    player.vy = 0;
    
    // Clear drops
    drops = [];
    
    // Spawn Enemies based on stage difficulty
    enemies = [];
    if (!isLabyrinth) {
        let numEnemies = 5 + (currentMapStageIndex * 2); 
        for(let i=0; i<numEnemies; i++) {
            let ex = randInt(10, mapWidth - 5);
            let ey = randInt(5, mapHeight - 5);
            if (mapGrid[ey][ex] === TILE_GROUND) {
                enemies.push(createEnemy(ex, ey));
            }
        }
        if (enemies.length > 0) {
            enemies[randInt(0, enemies.length - 1)].holdsKey = true;
        }
    }
    createMapCache();
    rebuildMapCache();
};

const generateLabyrinthMap = () => {
    // Fill completely with trees
    mapGrid = Array.from({ length: mapHeight }, () => Array(mapWidth).fill(TILE_OBSTACLE_TREE));
    
    // Recursive Backtracker
    const stack = [];
    const startX = 2, startY = 2;
    mapGrid[startY][startX] = TILE_GROUND;
    stack.push({ x: startX, y: startY });
    
    while(stack.length > 0) {
        const current = stack[stack.length - 1];
        const dirs = [
            { dx: 0, dy: -2 }, { dx: 0, dy: 2 },
            { dx: -2, dy: 0 }, { dx: 2, dy: 0 }
        ].sort(() => Math.random() - 0.5);
        
        let carved = false;
        for (let dir of dirs) {
            const nx = current.x + dir.dx;
            const ny = current.y + dir.dy;
            if (nx > 0 && nx < mapWidth - 1 && ny > 0 && ny < mapHeight - 1 && mapGrid[ny][nx] === TILE_OBSTACLE_TREE) {
                mapGrid[ny][nx] = TILE_GROUND;
                mapGrid[current.y + dir.dy/2][current.x + dir.dx/2] = TILE_GROUND;
                stack.push({ x: nx, y: ny });
                carved = true;
                break;
            }
        }
        if (!carved) stack.pop();
    }
    
    // Create some open rooms randomly
    for(let i=0; i<10; i++) {
        let rx = randInt(2, mapWidth - 6);
        let ry = randInt(2, mapHeight - 6);
        for(let dy=0; dy<4; dy++) {
            for(let dx=0; dx<4; dx++) {
                mapGrid[ry+dy][rx+dx] = TILE_GROUND;
            }
        }
    }
    
    // Place Spikes
    for (let i = 0; i < 20; i++) {
        let sx = randInt(2, mapWidth - 3);
        let sy = randInt(2, mapHeight - 3);
        if (mapGrid[sy][sx] === TILE_GROUND && (Math.abs(sx - startX) > 3 || Math.abs(sy - startY) > 3)) {
            mapGrid[sy][sx] = TILE_SPIKE;
        }
    }

    // Place Chest (NPC) on a valid ground tile far away
    let placedNPC = false;
    while (!placedNPC) {
        let nx = randInt(Math.floor(mapWidth/2), mapWidth - 2);
        let ny = randInt(Math.floor(mapHeight/2), mapHeight - 2);
        if (mapGrid[ny][nx] === TILE_GROUND) {
            mapGrid[ny][nx] = TILE_NPC;
            npcPos = { x: nx, y: ny };
            placedNPC = true;
        }
    }

    // Place Portal near the chest
    let placedPortal = false;
    while (!placedPortal) {
        let px = randInt(1, mapWidth - 2);
        let py = randInt(1, mapHeight - 2);
        if (mapGrid[py][px] === TILE_GROUND && Math.abs(px - npcPos.x) > 5) {
            const isCleared = props.clearedStageUuids.includes(activeStageUuid);
            mapGrid[py][px] = isCleared ? TILE_PORTAL_OPEN : TILE_PORTAL_LOCKED;
            portalPos = { x: px, y: py };
            placedPortal = true;
        }
    }
};

const generateMap = () => {
    mapGrid = Array.from({ length: mapHeight }, () => Array(mapWidth).fill(TILE_GROUND));

    // Fill edges with trees
    for (let y = 0; y < mapHeight; y++) {
        for (let x = 0; x < mapWidth; x++) {
            if (y === 0 || y === mapHeight - 1 || x === 0 || x === mapWidth - 1) {
                mapGrid[y][x] = TILE_OBSTACLE_TREE;
            } else {
                const r = Math.random();
                const obstacleDensity = isTouchDevice.value ? 0.045 : 0.08;
                const waterDensity = isTouchDevice.value ? 0.02 : 0.05;
                if (r < waterDensity) mapGrid[y][x] = TILE_WATER;
                else if (r < waterDensity + obstacleDensity) mapGrid[y][x] = TILE_OBSTACLE_TREE;
            }
        }
    }

    // Clear spawn area
    for (let dy = 1; dy <= 4; dy++) {
        for (let dx = 1; dx <= 4; dx++) {
            mapGrid[dy][dx] = TILE_GROUND;
        }
    }

    // Place House
    let placedHouse = false;
    while (!placedHouse) {
        let hx = randInt(5, mapWidth - 10);
        let hy = randInt(5, mapHeight - 10);
        // Ensure space for a 3x3 house
        let canPlace = true;
        for (let y = hy; y < hy + 3; y++) {
            for (let x = hx; x < hx + 3; x++) {
                if (mapGrid[y][x] !== TILE_GROUND) canPlace = false;
            }
        }
        if (canPlace) {
            for (let y = hy; y < hy + 3; y++) {
                for (let x = hx; x < hx + 3; x++) {
                    mapGrid[y][x] = 10; // house body
                }
            }
            mapGrid[hy+2][hx+1] = 11; // door
            placedHouse = true;
        }
    }
    
    // Place Locked Chest
    let placedLockedChest = false;
    while (!placedLockedChest) {
        let cx = randInt(2, mapWidth - 3);
        let cy = randInt(2, mapHeight - 3);
        if (mapGrid[cy][cx] === TILE_GROUND && mapGrid[cy][cx] !== 10 && mapGrid[cy][cx] !== 11) {
            mapGrid[cy][cx] = TILE_LOCKED_CHEST;
            placedLockedChest = true;
        }
    }

    // Place Chest (NPC) far awayInitially locked as ground until enemies are defeated, but we mark it)
    let placedNPC = false;
    while (!placedNPC) {
        let nx = randInt(5, mapWidth - 5);
        let ny = randInt(5, mapHeight - 5);
        if (mapGrid[ny][nx] === TILE_GROUND) {
            mapGrid[ny][nx] = TILE_NPC;
            npcPos = { x: nx, y: ny };
            // clear around
            mapGrid[ny+1][nx] = TILE_GROUND;
            mapGrid[ny-1][nx] = TILE_GROUND;
            mapGrid[ny][nx+1] = TILE_GROUND;
            mapGrid[ny][nx-1] = TILE_GROUND;
            placedNPC = true;
        }
    }

    // Place Portal
    let placedPortal = false;
    while (!placedPortal) {
        let px = randInt(mapWidth - 6, mapWidth - 2);
        let py = randInt(mapHeight - 6, mapHeight - 2);
        if (mapGrid[py][px] === TILE_GROUND && Math.abs(px - npcPos.x) > 5) {
            const isCleared = props.clearedStageUuids.includes(activeStageUuid);
            mapGrid[py][px] = isCleared ? TILE_PORTAL_OPEN : TILE_PORTAL_LOCKED;
            portalPos = { x: px, y: py };
            placedPortal = true;
            mapGrid[py][px-1] = TILE_GROUND;
            mapGrid[py+1][px] = TILE_GROUND;
            mapGrid[py-1][px] = TILE_GROUND;
        }
    }
};

watch(() => props.clearedStageUuids, (newUuids) => {
    if (newUuids.includes(activeStageUuid) && mapGrid.length > 0) {
        mapGrid[portalPos.y][portalPos.x] = TILE_PORTAL_OPEN;
        rebuildMapCache();
    }
}, { deep: true });

onMounted(() => {
    ctx = canvasRef.value.getContext('2d');
    isTouchDevice.value = typeof window !== 'undefined' && (
        window.matchMedia('(pointer: coarse)').matches || navigator.maxTouchPoints > 0
    );
    
    // Attempt to start BGM if browser allows it on mount, otherwise we rely on the first interaction
    audio.bgm.play().catch(() => {
        // Autoplay blocked, will play on first key press
        window.addEventListener('keydown', () => audio.bgm.play(), { once: true });
    });
    
    let firstUncleared = 0;
    for (let i = 0; i < props.stages.length; i++) {
        if (!props.clearedStageUuids.includes(props.stages[i].uuid)) {
            firstUncleared = i;
            break;
        }
    }
    currentMapStageIndex = firstUncleared;
    setupMapForCurrentStage();
    visibilityHandler = () => {
        if (document.hidden) {
            lastFrameTs = 0;
        }
    };
    document.addEventListener('visibilitychange', visibilityHandler);

    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
    
    gameLoop();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    if (visibilityHandler) {
        document.removeEventListener('visibilitychange', visibilityHandler);
        visibilityHandler = null;
    }
});

const handleKeyDown = (e) => {
    if (!props.isActive) {
        keys.w = false; keys.a = false; keys.s = false; keys.d = false; keys.space = false;
        return;
    }
    const k = e.key.toLowerCase();
    if (k === 'w' || e.key === 'arrowup') { keys.w = true; e.preventDefault(); }
    if (k === 'a' || e.key === 'arrowleft') { keys.a = true; e.preventDefault(); }
    if (k === 's' || e.key === 'arrowdown') { keys.s = true; e.preventDefault(); }
    if (k === 'd' || e.key === 'arrowright') { keys.d = true; e.preventDefault(); }
    if (k === ' ' || e.key === 'enter') {
        keys.space = true;
        e.preventDefault();
        if (interactCooldown.value <= 0) {
            handleAction();
        }
    }
};

const handleKeyUp = (e) => {
    const k = e.key.toLowerCase();
    if (k === 'w' || e.key === 'arrowup') keys.w = false;
    if (k === 'a' || e.key === 'arrowleft') keys.a = false;
    if (k === 's' || e.key === 'arrowdown') keys.s = false;
    if (k === 'd' || e.key === 'arrowright') keys.d = false;
    if (k === ' ' || e.key === 'enter') keys.space = false;
};

const getFacingTile = () => {
    let tx = Math.floor(player.px / tileSize);
    let ty = Math.floor(player.py / tileSize);
    if (player.direction === 1) ty -= 1; // up
    if (player.direction === 0) ty += 1; // down
    if (player.direction === 2) tx -= 1; // left
    if (player.direction === 3) tx += 1; // right
    return { x: tx, y: ty };
};

const handleAction = () => {
    interactCooldown.value = 20;
    player.isAttacking = true;
    player.attackFrame = 10;
    
    audio.slash.currentTime = 0;
    audio.slash.play().catch(e => {});
    
    // Check if hitting enemy
    const attackRange = tileSize * 1.8;
    let hitSomething = false;
    
    for (let e of enemies) {
        if (e.dead) continue;
        const dist = Math.hypot(e.px - player.px, e.py - player.py);
        
        // Precise angle check for a 90-degree attack wedge
        const dx = e.px - player.px;
        const dy = e.py - player.py;
        const angle = Math.atan2(dy, dx);
        
        let targetAngle = 0;
        if (player.direction === 0) targetAngle = Math.PI/2; // Down
        if (player.direction === 1) targetAngle = -Math.PI/2; // Up
        if (player.direction === 2) targetAngle = Math.PI; // Left
        if (player.direction === 3) targetAngle = 0; // Right
        
        let angleDiff = Math.abs(angle - targetAngle);
        if (angleDiff > Math.PI) angleDiff = 2 * Math.PI - angleDiff;
        
        // 45 degrees either side of the target angle gives a 90-degree wedge
        let isFacing = (angleDiff <= Math.PI/4);
        
        if (dist < attackRange && isFacing) {
            let damage = player.buffOneHitTimer > 0 ? 999 : 1;
            e.hp -= damage;
            floatingTexts.push({ text: "-" + damage, x: e.px, y: e.py - 30, life: 30, color: '#ff4757' });
            e.px += (e.px > player.px ? 10 : -10); // Knockback
            e.py += (e.py > player.py ? 10 : -10);
            if (e.hp <= 0) {
                e.dead = true;
                if (e.holdsKey) {
                    drops.push({ x: e.px, y: e.py - 10, type: 4 }); // Silver Key
                }
                // EXP Gem 100% chance (type 3)
                drops.push({ x: e.px + randInt(-15, 15), y: e.py + randInt(-15, 15), type: 3 });
                // Random Drop Chance 40%
                if (Math.random() < 0.4) {
                    drops.push({ x: e.px, y: e.py, type: randInt(0, 2) });
                }
            }
            hitSomething = true;
            hitStopTimer = 3;
            for (let i = 0; i < 5; i++) {
                sparks.push({
                    x: e.px,
                    y: e.py,
                    vx: randInt(-30, 30) * 0.12,
                    vy: randInt(-30, 30) * 0.12,
                    life: 14,
                });
            }
            
            audio.hit.currentTime = 0;
            audio.hit.play().catch(e => {});
        }
    }
    
    if (hitSomething) return; // If we attacked, we don't interact

    // Check interaction with NPC/Chest
    const facing = getFacingTile();
    if (facing.x >= 0 && facing.x < mapWidth && facing.y >= 0 && facing.y < mapHeight) {
        const tile = mapGrid[facing.y][facing.x];
        if (tile === TILE_NPC) {
            const aliveEnemies = enemies.filter(e => !e.dead).length;
            if (aliveEnemies === 0) {
                emit('interact-terminal', activeStageUuid);
            } else {
                floatingTexts.push({ text: "Defeat all enemies first!", x: player.px, y: player.py - 40, life: 60, color: '#ff4757' });
            }
        } else if (tile === TILE_LOCKED_CHEST) {
            if (player.keys > 0) {
                player.keys--;
                mapGrid[facing.y][facing.x] = TILE_OPEN_CHEST;
                rebuildMapCache();
                floatingTexts.push({ text: "-1 Silver Key", x: player.px, y: player.py - 40, life: 60, color: '#bdc3c7' });
                // Loot explosion
                for (let i = 0; i < 10; i++) {
                    drops.push({ x: facing.x * tileSize + tileSize/2 + randInt(-40, 40), y: facing.y * tileSize + tileSize/2 + randInt(-40, 40), type: 3 }); // EXP Gems
                }
                drops.push({ x: facing.x * tileSize + tileSize/2, y: facing.y * tileSize + tileSize/2 + 20, type: 0 }); // Heal
            } else {
                floatingTexts.push({ text: "LOCKED! Need Silver Key", x: player.px, y: player.py - 40, life: 60, color: '#e74c3c' });
            }
        }
    }
};

const getFacingTileType = () => {
    const facing = getFacingTile();
    if (facing.x < 0 || facing.x >= mapWidth || facing.y < 0 || facing.y >= mapHeight) return null;
    return mapGrid[facing.y][facing.x];
};

const updateActionPrompt = () => {
    const aliveEnemies = enemies.filter((e) => !e.dead).length;
    const facingTile = getFacingTileType();
    if (facingTile === TILE_NPC) {
        actionPrompt.value = aliveEnemies === 0 ? 'Interact Terminal' : 'Attack';
        return;
    }
    if (facingTile === TILE_LOCKED_CHEST) {
        actionPrompt.value = player.keys > 0 ? 'Open Chest' : 'Need Key';
        return;
    }
    actionPrompt.value = 'Attack';
};

const updateMissionUiState = () => {
    const aliveEnemies = enemies.filter((e) => !e.dead).length;
    const stageLabel = `Stage ${currentMapStageIndex + 1}/${props.stages.length}`;
    if (aliveEnemies > 0) {
        objectiveLabel.value = `${stageLabel}: Defeat enemies`;
    } else if (!props.clearedStageUuids.includes(activeStageUuid)) {
        objectiveLabel.value = `${stageLabel}: Reach terminal`;
    } else {
        objectiveLabel.value = `${stageLabel}: Find open portal`;
    }
    const progressSnapshot = `${aliveEnemies}:${player.keys}:${props.clearedStageUuids.includes(activeStageUuid)}:${currentMapStageIndex}`;
    if (progressSnapshot === lastProgressSnapshot) {
        stuckFrames++;
    } else {
        stuckFrames = 0;
        lastProgressSnapshot = progressSnapshot;
        showHint.value = false;
    }
    if (stuckFrames > 600) {
        showHint.value = true;
        hintToastFrames = 180;
    }
    if (hintToastFrames > 0) {
        hintToastFrames--;
    } else {
        showHint.value = false;
    }
};

const resetMoveKeys = () => {
    keys.w = false;
    keys.a = false;
    keys.s = false;
    keys.d = false;
};

const updateMoveFromVector = (dx, dy) => {
    resetMoveKeys();
    if (Math.abs(dx) < moveDeadzone && Math.abs(dy) < moveDeadzone) return;
    keys.w = dy < -moveDeadzone;
    keys.s = dy > moveDeadzone;
    keys.a = dx < -moveDeadzone;
    keys.d = dx > moveDeadzone;
};

const updateJoystickStyles = () => {
    joystickBaseStyle.value = {
        left: `${joystick.baseX - 48}px`,
        top: `${joystick.baseY - 48}px`,
    };
    joystickKnobStyle.value = {
        left: `${joystick.knobX - 24}px`,
        top: `${joystick.knobY - 24}px`,
    };
};

const handleMoveTouchStart = (e) => {
    if (!props.isActive) return;
    const touch = e.changedTouches[0];
    joystick.active = true;
    joystick.pointerId = touch.identifier;
    joystick.baseX = touch.clientX;
    joystick.baseY = touch.clientY;
    joystick.knobX = touch.clientX;
    joystick.knobY = touch.clientY;
    joystickVisible.value = true;
    updateJoystickStyles();
};

const handleMoveTouchMove = (e) => {
    if (!joystick.active) return;
    const touch = Array.from(e.changedTouches).find((t) => t.identifier === joystick.pointerId);
    if (!touch) return;
    const rawDx = touch.clientX - joystick.baseX;
    const rawDy = touch.clientY - joystick.baseY;
    const magnitude = Math.hypot(rawDx, rawDy);
    const scale = magnitude > moveClamp ? moveClamp / magnitude : 1;
    const dx = rawDx * scale;
    const dy = rawDy * scale;
    joystick.knobX = joystick.baseX + dx;
    joystick.knobY = joystick.baseY + dy;
    updateJoystickStyles();
    updateMoveFromVector(dx, dy);
};

const handleMoveTouchEnd = (e) => {
    if (!joystick.active) return;
    const touch = Array.from(e.changedTouches).find((t) => t.identifier === joystick.pointerId);
    if (!touch) return;
    joystick.active = false;
    joystick.pointerId = null;
    joystickVisible.value = false;
    resetMoveKeys();
};

const handleTouchAction = (e) => {
    e.preventDefault();
    if (!props.isActive || interactCooldown.value > 0) return;
    handleAction();
};

const isSolid = (tx, ty) => {
    if (tx < 0 || tx >= mapWidth || ty < 0 || ty >= mapHeight) return true;
    const tile = mapGrid[ty][tx];
    return tile === TILE_OBSTACLE_TREE || tile === TILE_WATER || tile === TILE_NPC || tile === TILE_PORTAL_LOCKED || tile === 10 || tile === 11 || tile === TILE_LOCKED_CHEST || tile === TILE_OPEN_CHEST;
};

const checkCollision = (newPx, newPy) => {
    const r = player.size / 2 - 2;
    const left = Math.floor((newPx - r) / tileSize);
    const right = Math.floor((newPx + r - 0.1) / tileSize);
    const top = Math.floor((newPy - r) / tileSize);
    const bottom = Math.floor((newPy + r - 0.1) / tileSize);

    return isSolid(left, top) || isSolid(right, top) || isSolid(left, bottom) || isSolid(right, bottom);
};

const update = (dt = 1) => {
    if (!props.isActive) return;
    
    if (player.health <= 0 && !player.isDead) {
        player.isDead = true;
        player.health = 0;
    }
    
    if (player.isDead) return; // Stop logic if dead
    
    timeAnim += dt;
    if (interactCooldown.value > 0) interactCooldown.value = Math.max(0, interactCooldown.value - dt);
    
    if (player.trapCooldown > 0) player.trapCooldown = Math.max(0, player.trapCooldown - dt);
    if (player.buffSpeedTimer > 0) player.buffSpeedTimer = Math.max(0, player.buffSpeedTimer - dt);
    if (player.buffOneHitTimer > 0) player.buffOneHitTimer = Math.max(0, player.buffOneHitTimer - dt);
    
    if (player.attackFrame > 0) {
        player.attackFrame -= dt;
        if (player.attackFrame <= 0) player.isAttacking = false;
    }
    updateActionPrompt();
    updateMissionUiState();

    // Process floating texts
    for (let i = floatingTexts.length - 1; i >= 0; i--) {
        let ft = floatingTexts[i];
        ft.life -= dt;
        ft.y -= 0.5 * dt;
        if (ft.life <= 0) floatingTexts.splice(i, 1);
    }
    for (let i = sparks.length - 1; i >= 0; i--) {
        const s = sparks[i];
        s.x += s.vx * dt;
        s.y += s.vy * dt;
        s.vx *= 0.93;
        s.vy *= 0.93;
        s.life -= dt;
        if (s.life <= 0) sparks.splice(i, 1);
    }
    if (hitStopTimer > 0) {
        hitStopTimer = Math.max(0, hitStopTimer - dt);
        return;
    }

    // Process Drops pickup
    for (let i = drops.length - 1; i >= 0; i--) {
        let d = drops[i];
        let dist = Math.hypot(player.px - d.x, player.py - d.y);
        if (dist < tileSize) {
            let t = "";
            let color = '#ffffff';
            if (d.type === 0) { player.health = Math.min(player.maxHealth, player.health + 3); t = "+3 HP"; color = '#ff4757'; } // Heal
            if (d.type === 1) { player.buffSpeedTimer = 300; t = "SPEED UP!"; color = '#1e90ff'; } // Speed 5 sec
            if (d.type === 2) { player.buffOneHitTimer = 300; t = "1-HIT KILL!"; color = '#ffa502'; } // 1-Hit 5 sec
            if (d.type === 3) { 
                player.exp += 1; t = "+1 EXP"; color = '#2ed573'; 
                if (player.exp >= player.level * 5) {
                    player.level++;
                    player.maxHealth += 2;
                    player.health = player.maxHealth;
                    player.exp = 0;
                    floatingTexts.push({ text: "LEVEL UP!", x: player.px, y: player.py - 60, life: 90, color: '#f1c40f' });
                }
            }
            if (d.type === 4) { player.keys++; t = "+1 Silver Key"; color = '#ecf0f1'; }
            floatingTexts.push({ text: t, x: player.px, y: player.py - 30, life: 60, color: color });
            drops.splice(i, 1);
        }
    }

    // Spikes Trap logic
    if (isLabyrinth && player.trapCooldown <= 0) {
        const tx = Math.floor(player.px / tileSize);
        const ty = Math.floor(player.py / tileSize);
        if (mapGrid[ty] && mapGrid[ty][tx] === TILE_SPIKE) {
            const isSpikeUp = Math.sin(timeAnim * 0.05) > 0;
            if (isSpikeUp) {
                player.health = Math.max(0, player.health - 2); // Traps deal 2 damage
                screenShake = 15;
                player.trapCooldown = 60;
                floatingTexts.push({ text: "-2", x: player.px, y: player.py - 30, life: 30, color: '#ff4757' });
                emit('update-integrity', player.health);
            }
        }
    }

    let targetDx = 0;
    let targetDy = 0;
    player.isMoving = false;
    
    let currentSpeed = player.buffSpeedTimer > 0 ? player.speed * 2 : player.speed;
    
    if (!player.isAttacking) {
        if (keys.w) { targetDy -= currentSpeed; player.direction = 1; player.isMoving = true; }
        if (keys.s) { targetDy += currentSpeed; player.direction = 0; player.isMoving = true; }
        if (keys.a) { targetDx -= currentSpeed; player.direction = 2; player.isMoving = true; }
        if (keys.d) { targetDx += currentSpeed; player.direction = 3; player.isMoving = true; }
    }

    if (targetDx !== 0 && targetDy !== 0) {
        targetDx *= 0.7071;
        targetDy *= 0.7071;
    }
    const smooth = Math.min(1, 0.22 * dt);
    player.vx += (targetDx - player.vx) * smooth;
    player.vy += (targetDy - player.vy) * smooth;
    const dx = player.vx * dt;
    const dy = player.vy * dt;

    if (player.isMoving || Math.abs(player.vx) > 0.2 || Math.abs(player.vy) > 0.2) {
        walkAnimTimer += dt;
        if (walkAnimTimer >= 8) {
            player.frame = (player.frame + 1) % 4; // 4 walk frames
            walkAnimTimer = 0;
        }
    } else {
        player.frame = 0; // idle frame
    }

    if (dx !== 0) {
        if (!checkCollision(player.px + dx, player.py)) {
            player.px += dx;
        }
    }
    if (dy !== 0) {
        if (!checkCollision(player.px, player.py + dy)) {
            player.py += dy;
        }
    }
    
    // Update Enemies
    enemyThinkTimer += dt;
    const shouldThinkEnemies = enemyThinkTimer >= 2;
    if (shouldThinkEnemies) enemyThinkTimer = 0;
    for (let e of enemies) {
        if (e.dead) continue;
        
        if (e.cooldown > 0) e.cooldown = Math.max(0, e.cooldown - dt);
        
        const dist = Math.hypot(player.px - e.px, player.py - e.py);
        if (dist < tileSize * 6 && shouldThinkEnemies) { // Aggro range
            let edx = 0;
            let edy = 0;
            if (player.px > e.px) { edx += e.speed * dt; e.direction = 3; }
            if (player.px < e.px) { edx -= e.speed * dt; e.direction = 2; }
            if (player.py > e.py) { edy += e.speed * dt; e.direction = 0; }
            if (player.py < e.py) { edy -= e.speed * dt; e.direction = 1; }
            
            if (!checkCollision(e.px + edx, e.py)) e.px += edx;
            if (!checkCollision(e.px, e.py + edy)) e.py += edy;
            
            enemyAnimTimer += dt;
            if (enemyAnimTimer >= 10) {
                e.frame = (e.frame + 1) % 4;
                enemyAnimTimer = 0;
            }
            
            // Damage player
            if (dist < tileSize * 0.8 && e.cooldown <= 0) {
                player.health = Math.max(0, player.health - 1);
                e.cooldown = 60; // 1 second cooldown
                screenShake = 15; // Screen shake juice
                hitStopTimer = 2;
                floatingTexts.push({ text: "-1", x: player.px, y: player.py - 30, life: 30, color: '#ff4757' });
                // Emit event to update backend integrity if needed, or just let local state sync.
                // In our case `Show.vue` manages integrity. Let's emit an event.
                emit('update-integrity', player.health);
            }
        }
    }
    
    // Check Portal
    const tx = Math.floor(player.px / tileSize);
    const ty = Math.floor(player.py / tileSize);
    if (ty >= 0 && ty < mapHeight && mapGrid[ty][tx] === TILE_PORTAL_OPEN) {
        if (currentMapStageIndex >= props.stages.length - 1) {
            emit('reach-exit');
        } else {
            currentMapStageIndex++;
            setupMapForCurrentStage();
        }
    }
};

const draw = () => {
    const canvas = canvasRef.value;
    if (!canvas || assetsLoaded < totalAssets) return;
    
    const container = canvas.parentElement;
    if (canvas.width !== container.clientWidth || canvas.height !== container.clientHeight) {
        canvas.width = container.clientWidth;
        canvas.height = container.clientHeight;
    }

    const cw = canvas.width;
    const ch = canvas.height;

    // Draw background outer
    ctx.fillStyle = '#000000';
    ctx.fillRect(0, 0, cw, ch);

    // Camera offset (center player)
    let offsetX = Math.floor(cw / 2 - player.px);
    let offsetY = Math.floor(ch / 2 - player.py);

    if (screenShake > 0) {
        offsetX += (Math.random() - 0.5) * 10;
        offsetY += (Math.random() - 0.5) * 10;
        screenShake--;
    }

    ctx.save();
    ctx.translate(offsetX, offsetY);

    if (mapCacheDirty && assetsLoaded >= totalAssets) {
        rebuildMapCache();
    }
    if (mapCacheCanvas && !mapCacheDirty) {
        ctx.drawImage(mapCacheCanvas, 0, 0);
    }

    // Dynamic tiles + overlays only
    const startCol = Math.floor(Math.max(0, -offsetX / tileSize));
    const endCol = Math.min(mapWidth, startCol + Math.ceil(cw / tileSize) + 1);
    const startRow = Math.floor(Math.max(0, -offsetY / tileSize));
    const endRow = Math.min(mapHeight, startRow + Math.ceil(ch / tileSize) + 1);

    for (let y = startRow; y < endRow; y++) {
        for (let x = startCol; x < endCol; x++) {
            const tile = mapGrid[y][x];
            const px = x * tileSize;
            const py = y * tileSize;

            if (tile === TILE_WATER) {
                const bnc = Math.sin(timeAnim * 0.05) * 4;
                ctx.drawImage(assets.water, px, py + bnc, tileSize, tileSize);
            } else if (tile === TILE_NPC) {
                // Chest
                const aliveEnemies = enemies.filter(e => !e.dead).length;
                if (aliveEnemies > 0) {
                    // Draw warning
                    ctx.fillStyle = '#e74c3c';
                    ctx.font = '12px "Press Start 2P"';
                    ctx.fillText('Defeat enemies!', px - 10, py - 10);
                } else {
                    ctx.drawImage(assets.chest, px, py, tileSize, tileSize);
                    const bounce = Math.sin(timeAnim * 0.1) * 3;
                    ctx.fillStyle = '#f1c40f';
                    ctx.font = '14px "Press Start 2P"';
                    ctx.fillText('!', px + tileSize/2 - 5, py - 5 + bounce);
                }
            } else if (tile === TILE_SPIKE) {
                const isSpikeUp = Math.sin(timeAnim * 0.05) > 0;
                if (isSpikeUp) {
                    ctx.fillStyle = '#7f8c8d';
                    for(let i=0; i<3; i++) {
                        for(let j=0; j<3; j++) {
                            ctx.beginPath();
                            ctx.moveTo(px + 8 + i*12, py + 12 + j*12);
                            ctx.lineTo(px + 12 + i*12, py + j*12);
                            ctx.lineTo(px + 16 + i*12, py + 12 + j*12);
                            ctx.fill();
                        }
                    }
                } else {
                    ctx.fillStyle = '#2c3e50';
                    for(let i=0; i<3; i++) {
                        for(let j=0; j<3; j++) {
                            ctx.fillRect(px + 10 + i*12, py + 10 + j*12, 4, 4);
                        }
                    }
                }
            }
        }
    }
    
    // Draw Enemies (Slime SVG)
    for (let e of enemies) {
        if (e.dead) continue;
        
        let eBnc = Math.sin(timeAnim * 0.2 + e.px) * 5;
        
        if (e.cooldown > 40) {
            ctx.globalAlpha = 0.5; // flash when hit
        }
        
        ctx.drawImage(assets.enemy, e.px - tileSize/2, e.py - tileSize/2 + eBnc, tileSize, tileSize);
        ctx.globalAlpha = 1.0;
        
        // HP Bar
        ctx.fillStyle = '#e74c3c';
        ctx.fillRect(e.px - 10, e.py - 20, 20, 4);
        ctx.fillStyle = '#2ecc71';
        ctx.fillRect(e.px - 10, e.py - 20, (Math.max(0, e.hp) / 3) * 20, 4);
    }

    // Draw Drops
    for (let d of drops) {
        const dBnc = Math.sin(timeAnim * 0.15 + d.x) * 3;
        ctx.beginPath();
        if (d.type === 0) ctx.fillStyle = '#ff4757'; // Red (Heal)
        if (d.type === 1) ctx.fillStyle = '#1e90ff'; // Blue (Speed)
        if (d.type === 2) ctx.fillStyle = '#ffa502'; // Gold (1-Hit)
        if (d.type === 3) ctx.fillStyle = '#2ed573'; // Green (EXP)
        if (d.type === 4) ctx.fillStyle = '#ecf0f1'; // Silver (Key)
        ctx.shadowColor = ctx.fillStyle;
        ctx.shadowBlur = 4;
        
        if (d.type === 3) {
            ctx.moveTo(d.x, d.y + dBnc - 8);
            ctx.lineTo(d.x + 8, d.y + dBnc);
            ctx.lineTo(d.x, d.y + dBnc + 8);
            ctx.lineTo(d.x - 8, d.y + dBnc);
        } else if (d.type === 4) {
            ctx.fillRect(d.x - 8, d.y + dBnc - 2, 16, 4);
            ctx.fillRect(d.x + 4, d.y + dBnc + 2, 4, 4);
        } else {
            ctx.arc(d.x, d.y + dBnc, 8, 0, Math.PI*2);
        }
        
        if (d.type !== 4) ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.shadowBlur = 0;
    }
    for (const s of sparks) {
        ctx.strokeStyle = `rgba(255, 240, 180, ${Math.max(0, s.life / 14)})`;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(s.x, s.y);
        ctx.lineTo(s.x - s.vx * 2, s.y - s.vy * 2);
        ctx.stroke();
    }

    // Player drawing properties calculated earlier
    let drawPx = player.px;
    let drawPy = player.py;
    
    if (player.isAttacking) {
        // Lunge forward slightly
        if (player.direction === 0) drawPy += 10;
        if (player.direction === 1) drawPy -= 10;
        if (player.direction === 2) drawPx -= 10;
        if (player.direction === 3) drawPx += 10;
    }

    // Draw the Player (from CodinGame assets)
    let bnc = player.isMoving ? Math.sin(timeAnim * 0.4) * 4 : 0;
    
    // Sprite Slicing (ninja-adventure characters: 4 cols, 7 rows. First 4 rows are D, U, L, R)
    let row = 0;
    if (player.direction === 0) row = 0; // down
    if (player.direction === 1) row = 1; // up
    if (player.direction === 2) row = 2; // left
    if (player.direction === 3) row = 3; // right
    
    // Character is 64x112 -> frame is 16x16 (4 columns, 7 rows)
    const frameW = 16;
    const frameH = 16;
    const sx = player.frame * frameW;
    const sy = row * frameH;
    
    if (player.isDead) {
        ctx.save();
        ctx.translate(drawPx, drawPy);
        ctx.rotate(Math.PI / 2);
        ctx.drawImage(
            assets.player,
            sx, sy, frameW, frameH,
            -tileSize/2, -tileSize/2, tileSize, tileSize
        );
        ctx.restore();
    } else {
        ctx.drawImage(
            assets.player,
            sx, sy, frameW, frameH,
            drawPx - tileSize/2, drawPy - tileSize/2 - 10 + bnc, tileSize, tileSize
        );
    }

    // Draw Attack Arc (Sword)
    if (player.isAttacking) {
        ctx.save();
        ctx.translate(drawPx, drawPy);
        
        // Rotate arc based on direction
        let angle = 0;
        if (player.direction === 1) angle = -Math.PI/2;
        if (player.direction === 2) angle = Math.PI;
        if (player.direction === 3) angle = 0;
        if (player.direction === 0) angle = Math.PI/2;
        
        ctx.rotate(angle);
        
        ctx.beginPath();
        // A stylized swoosh
        ctx.arc(0, 0, tileSize * 1.5, -Math.PI/4, Math.PI/4);
        ctx.lineWidth = 12;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.shadowColor = '#00ffff';
        ctx.shadowBlur = 10;
        ctx.lineCap = 'round';
        ctx.stroke();
        
        // Inner bright line
        ctx.beginPath();
        ctx.arc(0, 0, tileSize * 1.5, -Math.PI/4, Math.PI/4);
        ctx.lineWidth = 4;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();
        
        ctx.restore();
    }

    // Compass Arrow
    const aliveEnemiesForCompass = enemies.filter(e => !e.dead).length;
    if (aliveEnemiesForCompass === 0 && !isBlind && !player.isDead) {
        const isCleared = props.clearedStageUuids.includes(activeStageUuid);
        const targetPos = isCleared ? portalPos : npcPos;
        const tx = targetPos.x * tileSize + tileSize/2;
        const ty = targetPos.y * tileSize + tileSize/2;
        
        const angle = Math.atan2(ty - player.py, tx - player.px);
        
        ctx.save();
        ctx.translate(drawPx, drawPy - 10 + bnc);
        ctx.rotate(angle);
        
        // Draw Arrow pointing Right (because angle is based on right=0)
        ctx.beginPath();
        ctx.moveTo(40, 0);
        ctx.lineTo(30, -8);
        ctx.lineTo(30, 8);
        ctx.closePath();
        ctx.fillStyle = isCleared ? '#3498db' : '#f1c40f'; // Blue for portal, Gold for chest
        ctx.shadowColor = ctx.fillStyle;
        ctx.shadowBlur = 15;
        ctx.fill();
        ctx.restore();
    }
    
    // Draw Floating Texts
    for (let ft of floatingTexts) {
        ctx.fillStyle = ft.color || '#ffffff';
        ctx.globalAlpha = Math.min(1.0, ft.life / 30);
        ctx.font = '12px "Press Start 2P"';
        ctx.textAlign = 'center';
        ctx.fillText(ft.text, ft.x, ft.y);
        ctx.globalAlpha = 1.0;
    }
    
    ctx.restore(); // Restore camera translation

    // Draw Fog of War (Blind Mode)
    if (isBlind) {
        ctx.save();
        const fogGrad = ctx.createRadialGradient(
            cw / 2, ch / 2, 
            tileSize * 2.2, 
            cw / 2, ch / 2, 
            tileSize * 7.5
        );
        fogGrad.addColorStop(0, 'rgba(0,0,0,0)');
        fogGrad.addColorStop(1, 'rgba(0,0,0,0.78)');
        ctx.fillStyle = fogGrad;
        ctx.fillRect(0, 0, cw, ch);
        ctx.restore();
    }

    // UI Overlay (Fixed on screen)
    const aliveEnemies = enemies.filter(e => !e.dead).length;
    
    const safeTop = isTouchDevice.value ? 16 : 10;
    const safeRight = isTouchDevice.value ? 16 : 10;
    const leftPanelW = isTouchDevice.value ? 170 : 200;
    const leftPanelH = isTouchDevice.value ? 64 : 80;
    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    ctx.fillRect(10, safeTop, leftPanelW, leftPanelH);
    ctx.fillStyle = '#ffffff';
    ctx.font = isTouchDevice.value ? '9px "Press Start 2P"' : '10px "Press Start 2P"';
    ctx.textAlign = 'left';
    ctx.fillText(`Stage: ${currentMapStageIndex + 1}/${props.stages.length}`, 20, safeTop + 18);
    ctx.fillStyle = aliveEnemies > 0 ? '#e74c3c' : '#2ecc71';
    ctx.fillText(aliveEnemies > 0 ? `Enemies: ${aliveEnemies} left` : `Quest Ready!`, 20, safeTop + 36);
    ctx.fillStyle = '#e67e22';
    ctx.fillText(`Integrity: ${player.health}/10`, 20, safeTop + 54);
    
    // Controls & Buffs
    if (!isTouchDevice.value) {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
        ctx.fillRect(cw - 260 - safeRight, safeTop, 250, 100);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
        ctx.textAlign = 'left';
        ctx.fillText('WASD: Move', cw - 250 - safeRight, safeTop + 20);
        ctx.fillText('SPACE: Attack/Interact', cw - 250 - safeRight, safeTop + 45);
    }
    
    if (player.buffSpeedTimer > 0) {
        ctx.fillStyle = '#1e90ff';
        if (!isTouchDevice.value) {
            ctx.fillText(`SPD: ${Math.ceil(player.buffSpeedTimer/60)}s`, cw - 250 - safeRight, safeTop + 70);
        }
    }
    if (player.buffOneHitTimer > 0) {
        ctx.fillStyle = '#ffa502';
        if (!isTouchDevice.value) {
            ctx.fillText(`1-HIT: ${Math.ceil(player.buffOneHitTimer/60)}s`, cw - 140 - safeRight, safeTop + 70);
        }
    }
    if (player.keys > 0) {
        ctx.fillStyle = '#ecf0f1';
        if (!isTouchDevice.value) {
            ctx.fillText(`Keys: ${player.keys}`, cw - 250 - safeRight, safeTop + 90);
        }
    }

    ctx.textAlign = 'center';
    ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
    ctx.fillRect((cw / 2) - 130, safeTop, 260, 30);
    ctx.fillStyle = '#4fd1c5';
    const compactObjective = objectiveLabel.value
        .replace(/^Stage\s+\d+\/\d+:\s*/i, '')
        .replace('Defeat enemies', 'Defeat mobs')
        .replace('Reach terminal', 'Reach terminal')
        .replace('Find open portal', 'Find portal');
    ctx.fillText(compactObjective, cw / 2, safeTop + 20);
    if (showHint.value) {
        const toastW = 230;
        const toastH = 26;
        const toastX = (cw / 2) - (toastW / 2);
        const toastY = safeTop + 36;
        ctx.fillStyle = 'rgba(0, 0, 0, 0.78)';
        ctx.fillRect(toastX, toastY, toastW, toastH);
        ctx.fillStyle = '#f6ad55';
        ctx.fillText('Hint: follow compass', cw / 2, toastY + 17);
    }

    // Circular minimap for easier navigation
    const minimapRadius = isTouchDevice.value ? 58 : 70;
    const minimapCx = minimapRadius + 24;
    const minimapCy = ch - minimapRadius - 24;
    const worldRadiusTiles = isTouchDevice.value ? 9 : 11;
    const minimapScale = minimapRadius / worldRadiusTiles;

    ctx.save();
    ctx.beginPath();
    ctx.arc(minimapCx, minimapCy, minimapRadius, 0, Math.PI * 2);
    ctx.clip();
    ctx.fillStyle = 'rgba(5, 12, 22, 0.82)';
    ctx.fillRect(minimapCx - minimapRadius, minimapCy - minimapRadius, minimapRadius * 2, minimapRadius * 2);

    const pxTile = player.px / tileSize;
    const pyTile = player.py / tileSize;
    const minTileX = Math.max(0, Math.floor(pxTile - worldRadiusTiles));
    const maxTileX = Math.min(mapWidth - 1, Math.ceil(pxTile + worldRadiusTiles));
    const minTileY = Math.max(0, Math.floor(pyTile - worldRadiusTiles));
    const maxTileY = Math.min(mapHeight - 1, Math.ceil(pyTile + worldRadiusTiles));

    for (let ty = minTileY; ty <= maxTileY; ty++) {
        for (let tx = minTileX; tx <= maxTileX; tx++) {
            const tile = mapGrid[ty]?.[tx];
            const dx = (tx + 0.5 - pxTile) * minimapScale;
            const dy = (ty + 0.5 - pyTile) * minimapScale;
            const sx = minimapCx + dx;
            const sy = minimapCy + dy;
            const size = Math.max(2, minimapScale * 0.92);

            if (tile === TILE_OBSTACLE_TREE || tile === 10 || tile === 11) {
                ctx.fillStyle = '#64748b';
            } else if (tile === TILE_WATER) {
                ctx.fillStyle = '#38bdf8';
            } else if (tile === TILE_PORTAL_OPEN) {
                ctx.fillStyle = '#22d3ee';
            } else if (tile === TILE_PORTAL_LOCKED) {
                ctx.fillStyle = '#334155';
            } else if (tile === TILE_NPC) {
                ctx.fillStyle = '#facc15';
            } else if (tile === TILE_LOCKED_CHEST || tile === TILE_OPEN_CHEST) {
                ctx.fillStyle = '#cbd5e1';
            } else {
                ctx.fillStyle = '#1f2937';
            }
            ctx.fillRect(sx - size / 2, sy - size / 2, size, size);
        }
    }

    for (const enemy of enemies) {
        if (enemy.dead) continue;
        const exTile = enemy.px / tileSize;
        const eyTile = enemy.py / tileSize;
        const dx = (exTile - pxTile) * minimapScale;
        const dy = (eyTile - pyTile) * minimapScale;
        if ((dx * dx + dy * dy) <= (minimapRadius * minimapRadius)) {
            ctx.fillStyle = '#ef4444';
            ctx.beginPath();
            ctx.arc(minimapCx + dx, minimapCy + dy, 2.8, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    ctx.fillStyle = '#4ade80';
    ctx.beginPath();
    ctx.arc(minimapCx, minimapCy, 4.5, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.75)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(minimapCx, minimapCy, minimapRadius, 0, Math.PI * 2);
    ctx.stroke();

    // Game Over Overlay
    if (player.isDead) {
        ctx.fillStyle = 'rgba(0,0,0,0.8)';
        ctx.fillRect(0, 0, cw, ch);
        ctx.fillStyle = '#e74c3c';
        ctx.textAlign = 'center';
        ctx.font = '40px "Press Start 2P"';
        ctx.fillText('GAME OVER', cw/2, ch/2);
        ctx.fillStyle = '#ffffff';
        ctx.font = '16px "Press Start 2P"';
        ctx.fillText('Refresh page to restart.', cw/2, ch/2 + 40);
    }
};

const gameLoop = (ts = 0) => {
    if (!lastFrameTs) lastFrameTs = ts;
    const deltaMs = Math.min(40, Math.max(8, ts - lastFrameTs || BASE_FRAME_MS));
    lastFrameTs = ts;
    const dt = deltaMs / BASE_FRAME_MS;
    update(dt);
    draw();
    animationFrameId = requestAnimationFrame(gameLoop);
};
</script>

<template>
    <div class="fixed inset-0 w-full h-full bg-[#000] overflow-hidden flex items-center justify-center font-['Press_Start_2P'] z-[2147483647] touch-none game-shell">
        <canvas ref="canvasRef" class="block w-full h-full"></canvas>
        <div
            v-if="isTouchDevice"
            class="mobile-touch-layer"
            @touchstart.passive="handleMoveTouchStart"
            @touchmove.prevent="handleMoveTouchMove"
            @touchend="handleMoveTouchEnd"
            @touchcancel="handleMoveTouchEnd"
        ></div>
        <div v-if="isTouchDevice && joystickVisible" class="joystick-base" :style="joystickBaseStyle"></div>
        <div v-if="isTouchDevice && joystickVisible" class="joystick-knob" :style="joystickKnobStyle"></div>
        <button
            v-if="isTouchDevice"
            type="button"
            class="action-btn"
            :disabled="!isActive"
            :aria-label="actionPrompt"
            :title="actionPrompt"
            @touchstart="handleTouchAction"
            @click.prevent="handleTouchAction"
        >
            🗡
        </button>
    </div>
</template>

<style scoped>
.game-shell {
    padding-top: env(safe-area-inset-top, 0);
    padding-right: env(safe-area-inset-right, 0);
    padding-bottom: env(safe-area-inset-bottom, 0);
    padding-left: env(safe-area-inset-left, 0);
}

.mobile-touch-layer {
    position: absolute;
    inset: 0;
    z-index: 2;
}

.joystick-base {
    position: absolute;
    width: 96px;
    height: 96px;
    border-radius: 999px;
    background: rgba(12, 18, 28, 0.28);
    border: 2px solid rgba(255, 255, 255, 0.35);
    z-index: 3;
    pointer-events: none;
}

.joystick-knob {
    position: absolute;
    width: 48px;
    height: 48px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.85);
    z-index: 4;
    pointer-events: none;
}

.action-btn {
    position: absolute;
    right: calc(env(safe-area-inset-right, 0) + 18px);
    bottom: calc(env(safe-area-inset-bottom, 0) + 24px);
    width: 76px;
    height: 76px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.7);
    background: rgba(9, 14, 25, 0.64);
    color: #e6f4f1;
    font-size: 30px;
    line-height: 1;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
    touch-action: manipulation;
}

.action-btn:disabled {
    opacity: 0.5;
}
</style>
