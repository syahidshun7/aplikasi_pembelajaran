<script setup>

const props = defineProps(['hero', 'quests', 'logs']);

</script>



<template>

  <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">

   

    <div class="rpg-panel mb-6 flex flex-col md:flex-row items-center gap-6 border-cyan-500/50">

      <div class="w-20 h-20 border-4 border-cyan-400 bg-slate-800 shadow-[0_0_15px_rgba(78,212,212,0.3)]">

        <img src="https://api.dicebear.com/7.x/pixel-art/svg?seed=Adventurer" class="w-full h-full object-cover">

      </div>

      <div class="flex-1 w-full space-y-3">

        <div class="flex justify-between items-center">

          <span class="text-white text-xs">Hero Name: {{ hero.name }}</span>

          <span class="text-yellow-400">GOLD: {{ hero.gold }} G</span>

        </div>

        <div class="flex items-center gap-4">

          <span class="whitespace-nowrap">LVL: {{ hero.level }}</span>

          <div class="flex-1 h-5 bg-black border-2 border-slate-700 overflow-hidden">

            <div class="xp-bar-fill h-full transition-all duration-1000" :style="{ width: hero.exp + '%' }"></div>

          </div>

        </div>

        <div class="flex gap-10 text-slate-400 text-[8px]">

          <span>STR: {{ hero.str }}</span>

          <span>INT: {{ hero.int }}</span>

          <span class="text-cyan-500 uppercase">Status: Online</span>

        </div>

      </div>

    </div>



    <div class="grid grid-cols-12 gap-6">

      <div class="col-span-12 lg:col-span-3 rpg-panel flex flex-col gap-6">

        <h2 class="text-center text-white border-b-2 border-slate-700 pb-2">MENU</h2>

        <nav class="space-y-2">

          <div class="p-2 bg-cyan-900/30 border-r-4 border-cyan-400 flex justify-between cursor-pointer">

            <span>Quests</span> <span>▶</span>

          </div>

          <div class="p-2 opacity-30 cursor-not-allowed">Battle Arena</div>

          <div class="p-2 opacity-30 cursor-not-allowed">Inventory</div>

          <div class="p-2 opacity-30 cursor-not-allowed">Options</div>

        </nav>

        <div class="mt-auto space-y-2 pt-4">

          <button class="w-full btn-rpg text-[8px]">Save Game</button>

          <button class="w-full btn-rpg text-[8px] bg-slate-600 border-slate-800">Music: On</button>

        </div>

      </div>



      <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">

        <div class="rpg-panel flex-1 min-h-[300px]">

          <h2 class="text-cyan-400 mb-6 flex items-center gap-2">

            <span class="animate-pulse">●</span> ACTIVE QUESTS (Tasks)

          </h2>

          <div class="space-y-4">

            <div v-for="q in quests" :key="q.id" class="flex justify-between items-center p-3 border-b border-slate-800 hover:bg-white/5 transition-colors">

              <span :class="q.xp === 'High' ? 'text-red-400' : 'text-cyan-400'">

                {{ q.xp === 'High' ? '⚔' : '⚓' }} {{ q.title }}.....({{ q.xp }} XP)

              </span>

              <button :class="q.status === 'Done' ? 'bg-cyan-600 border-cyan-800 text-white' : ''" class="btn-rpg text-[8px]">

                {{ q.status }}

              </button>

            </div>

          </div>

        </div>



        <div class="rpg-panel h-32 overflow-y-auto border-slate-600">

          <h2 class="text-white text-[8px] mb-2 uppercase opacity-50">Battle Log</h2>

          <div class="space-y-1 text-[8px] text-slate-300">

            <p v-for="(log, i) in logs" :key="i" class="animate-in fade-in slide-in-from-left duration-500">

              > {{ log }}

            </p>

          </div>

        </div>

      </div>

    </div>

  </div>

</template>

