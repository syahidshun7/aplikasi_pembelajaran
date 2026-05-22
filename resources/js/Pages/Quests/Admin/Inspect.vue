<template>
    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b-4 border-slate-800 pb-6 gap-4">
                <div>
                    <h1 class="text-xl text-white uppercase mb-2 tracking-tighter">Manual_Inspection_Console</h1>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[8px] text-slate-500 italic">SUBMISSION_UUID: {{ submission.uuid.substring(0, 8) }}...</span>
                        <span class="text-[8px] bg-yellow-900/30 text-yellow-500 px-2 py-1 border border-yellow-700">
                            DIFFICULTY: {{ submission.quest.difficulty }}
                        </span>
                        <span class="text-[8px] bg-blue-900/30 text-blue-400 px-2 py-1 border border-blue-700 uppercase">
                            MAX_REWARD: {{ submission.quest.reward_gold }} G
                        </span>
                        <span class="text-[8px] bg-cyan-900/30 text-cyan-400 px-2 py-1 border border-cyan-700 uppercase">
                            MAX_EXP: {{ maxExpReward }} XP
                        </span>
                    </div>
                </div>
                <Link :href="route('admin.quests.submissions', { quest: submission.quest.uuid })"
                    class="text-[8px] bg-red-900/20 text-red-500 px-6 py-3 border-2 border-red-900 hover:bg-red-900 hover:text-white transition-all">
                    [ CLOSE_TERMINAL ]
                </Link>
            </div>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-yellow-500 uppercase tracking-widest">>> MISSION_OBJECTIVE_&_LOG</h3>
                    <span class="text-[8px] text-slate-500 italic font-bold uppercase">
                        Adventurer: {{ submission.user.name }}
                    </span>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1 space-y-4 border-b-2 lg:border-b-0 lg:border-r-2 border-slate-800 pb-6 lg:pb-0 lg:pr-6">
                        <p class="text-[8px] text-slate-500 uppercase italic">Quest_Title:</p>
                        <p class="text-white text-sm leading-relaxed mb-6">{{ submission.quest.title }}</p>

                        <p class="text-[8px] text-slate-500 uppercase italic">Objective_Brief:</p>
                        <p class="text-slate-400 font-sans text-xs leading-relaxed italic border-l-2 border-slate-700 pl-4">
                            "{{ submission.quest.description }}"
                        </p>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <p class="text-[8px] text-cyan-500 uppercase mb-2 italic animate-pulse">Artifact_Viewer_Active:</p>

                        <div v-if="submission.content"
                            class="bg-black border-2 border-slate-800 p-6 font-sans text-sm text-slate-300 whitespace-pre-wrap mb-4 shadow-inner max-h-96 overflow-y-auto custom-scrollbar">
                            {{ submission.content }}
                        </div>

                        <div v-if="taskQuestions.length"
                            class="bg-black border-2 border-slate-800 p-6 shadow-inner space-y-4">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                <p class="text-[8px] text-yellow-400 uppercase tracking-widest">
                                    >> TASK_BANK_QA_LOG
                                </p>
                                <p class="text-[7px] text-slate-500 uppercase italic">
                                    BANK: <span class="text-slate-300 font-bold">{{ taskBank?.name || '-' }}</span>
                                    <span v-if="taskBank?.assessment_type" class="text-slate-500">({{ taskBank.assessment_type }})</span>
                                </p>
                            </div>

                            <div class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                                <div v-for="(q, idx) in taskQuestions" :key="q.uuid"
                                    class="p-4 border border-slate-800 bg-slate-950/30">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[7px] text-slate-500 uppercase mb-2">
                                                Q{{ idx + 1 }} // {{ q.question_type || 'essay' }} // W: {{ q.weight || 1 }}
                                            </p>
                                            <p class="text-[13px] font-sans text-slate-200 break-words">
                                                {{ q.question_text }}
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right text-[7px] text-slate-500 uppercase">
                                            ID: {{ String(q.uuid || '').substring(0, 8) }}...
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <template v-if="q.question_type === 'multiple_choice'">
                                            <p class="text-[8px] text-slate-500 uppercase italic mb-2">SELECTED_ANSWER:</p>
                                            <p class="text-[12px] font-sans"
                                                :class="selectedAnswerFor(q) ? 'text-cyan-300' : 'text-red-400'">
                                                {{ selectedAnswerFor(q) || 'NOT_ANSWERED' }}
                                            </p>

                                            <div v-if="Array.isArray(q.options_json) && q.options_json.length"
                                                class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                                <div v-for="(opt, oidx) in q.options_json" :key="`${q.uuid}-opt-${oidx}`"
                                                    class="px-3 py-2 border text-[11px] font-sans break-words"
                                                    :class="String(opt) === String(selectedAnswerFor(q)) ? 'border-cyan-500 bg-cyan-500/10 text-cyan-200' : 'border-slate-800 bg-black/20 text-slate-400'">
                                                    {{ opt }}
                                                </div>
                                            </div>

                                            <p v-if="q.answer_key" class="mt-3 text-[8px] text-slate-500 uppercase italic">
                                                ANSWER_KEY: <span class="text-emerald-400 font-bold">{{ q.answer_key }}</span>
                                            </p>
                                        </template>

                                        <template v-else-if="q.question_type === 'platforming'">
                                            <p class="text-[8px] text-purple-400 uppercase italic mb-2">PLATFORMING_RESULT:</p>
                                            <div class="bg-black/40 border border-purple-800 p-4 font-sans text-[12px] text-slate-200 space-y-2">
                                                <template v-if="parsedGameAnswer(q)">
                                                    <p class="text-[10px] text-purple-300">
                                                        Score: <span class="font-bold">{{ parsedGameAnswer(q).score }}</span> / {{ parsedGameAnswer(q).total }}
                                                        · Level: <span class="font-bold">{{ parsedGameAnswer(q).level }}</span>
                                                    </p>
                                                    <div v-for="(a, ai) in (parsedGameAnswer(q).answers || [])" :key="ai" class="text-[10px]">
                                                        <span class="text-slate-500">Stage {{ ai + 1 }}:</span>
                                                        <span :class="a.correct ? 'text-emerald-400' : 'text-red-400'">{{ a.answer }}</span>
                                                        <span class="text-slate-600">({{ a.correct ? '✓' : '✗' }})</span>
                                                    </div>
                                                </template>
                                                <p v-else class="text-red-400">NOT_ANSWERED</p>
                                            </div>
                                        </template>

                                        <template v-else-if="q.question_type === 'word_match'">
                                            <p class="text-[8px] text-orange-400 uppercase italic mb-2">WORD_MATCH_RESULT:</p>
                                            <div class="bg-black/40 border border-orange-800 p-4 font-sans text-[12px] text-slate-200 space-y-2">
                                                <template v-if="parsedWordMatchAnswer(q)">
                                                    <p class="text-[10px]" :class="parsedWordMatchAnswer(q).complete ? 'text-emerald-400' : 'text-yellow-400'">
                                                        {{ parsedWordMatchAnswer(q).complete ? 'COMPLETED' : 'INCOMPLETE' }}
                                                    </p>
                                                    <p class="text-[10px] text-orange-300">
                                                        Placed: {{ (parsedWordMatchAnswer(q).placed || []).join(' · ') || '-' }}
                                                    </p>
                                                </template>
                                                <p v-else class="text-red-400">NOT_ANSWERED</p>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <p class="text-[8px] text-slate-500 uppercase italic mb-2">STUDENT_ANSWER:</p>
                                            <div class="bg-black/40 border border-slate-800 p-4 font-sans text-[12px] text-slate-200 whitespace-pre-wrap">
                                                {{ selectedAnswerFor(q) || 'NOT_ANSWERED' }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="submission.file_path" class="border-4 border-black bg-black shadow-2xl overflow-hidden">
                            <div v-if="isImage(submission.file_path)" class="p-2">
                                <img :src="attachmentPreviewUrl" class="w-full h-auto" alt="Submission attachment">
                            </div>
                            <div v-else-if="isPdf(submission.file_path)" class="h-[500px] w-full bg-slate-800">
                                <div v-if="isMobilePdfPreview" class="h-full w-full p-4 text-[8px] text-slate-300 uppercase border border-slate-700 bg-black">
                                    Preview PDF di mobile kadang dibatasi browser. Gunakan OPEN_ATTACHMENT untuk membuka viewer bawaan browser.
                                </div>
                                <iframe
                                    v-else
                                    :src="`${attachmentPreviewUrl}#toolbar=1&view=FitH`"
                                    class="w-full h-full"
                                    title="Submission PDF preview"
                                />
                            </div>
                            <div v-else class="p-4 bg-slate-900 text-[8px] text-slate-400 uppercase">
                                Preview not supported for this file type.
                            </div>
                            <div class="p-3 border-t border-slate-800 bg-slate-950 text-right">
                                <a :href="attachmentPreviewUrl" target="_blank"
                                    class="inline-block bg-cyan-900/40 border border-cyan-400 px-3 py-1 text-cyan-300 hover:bg-cyan-400 hover:text-black transition-all text-[8px] uppercase">
                                    OPEN_ATTACHMENT
                                </a>
                            </div>
                        </div>

                        <div v-if="submission.link"
                            class="p-4 bg-cyan-900/10 border-2 border-cyan-500/30 flex flex-col md:flex-row justify-between items-center gap-4">
                            <span class="text-[8px] text-cyan-400 font-mono break-all">{{ submission.link }}</span>
                            <a :href="submission.link" target="_blank"
                                class="bg-cyan-500 text-black px-4 py-2 text-[8px] font-bold hover:bg-white transition-colors whitespace-nowrap">
                                OPEN_EXTERNAL_SOURCE
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-green-500 uppercase tracking-widest">>> ACADEMIC_EVALUATION_&_REWARDS</h3>
                    <div class="flex items-center gap-3">
                        <span v-if="hasRubric" class="text-[8px] text-slate-400 uppercase italic">
                            RUBRIC: <span class="text-cyan-300 font-bold">{{ rubricTitle }}</span>
                            <span v-if="rubricSourceLabel" class="text-slate-500">({{ rubricSourceLabel }})</span>
                        </span>
                        <span v-else-if="isTaskBankSubmission" class="text-[8px] text-slate-400 uppercase italic">
                            QUESTION_BANK: <span class="text-yellow-400 font-bold">{{ taskBank?.name || '-' }}</span>
                            <span v-if="taskBankType" class="text-slate-500">({{ taskBankType }})</span>
                        </span>
                        <span v-else class="text-[8px] text-slate-400 uppercase italic">
                            MANUAL_SCORE: <span class="text-cyan-300 font-bold">1–100</span>
                        </span>
                        <button @click="scanWithAI" :disabled="isScanning || isPreparingAiPreview" 
                            class="text-[8px] bg-indigo-900/30 text-indigo-400 px-3 py-1 border border-indigo-700 hover:bg-indigo-500 hover:text-white transition-all disabled:opacity-50">
                            {{ isPreparingAiPreview ? 'PREPARING...' : (isScanning ? 'ANALYZING...' : '[ AI_ADVISOR_SCAN ]') }}
                        </button>
                    </div>
                </div>

                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                        
                        <div class="lg:col-span-7 space-y-3">
                            <template v-if="isTaskBankSubmission">
                                <div class="p-4 border-2 border-slate-800 bg-black/40">
                                    <div class="flex justify-between text-[8px] uppercase">
                                        <span class="text-slate-400 font-sans">AUTO_MCQ:</span>
                                        <span class="text-slate-200 font-bold">{{ taskBankMcqEarnedPoints }} / {{ taskBankMcqMaxPoints }} pts</span>
                                    </div>
                                    <div v-if="essayQuestions.length" class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">MANUAL_ESSAY:</span>
                                        <span class="text-slate-200 font-bold">{{ taskBankEssayEarnedPoints }} / {{ taskBankEssayMaxPoints }} pts</span>
                                    </div>
                                    <div class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">TOTAL_POINTS:</span>
                                        <span class="text-cyan-300 font-bold underline">{{ taskBankEarnedPoints }} / {{ taskBankMaxPoints }} pts</span>
                                    </div>
                                </div>

                                <div v-for="(q, idx) in taskQuestions" :key="q.uuid"
                                    class="p-4 border-2 border-slate-800 bg-black/40 hover:border-cyan-500 transition-all">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="min-w-0">
                                            <p class="text-[7px] text-slate-500 uppercase italic mb-2">
                                                Q{{ idx + 1 }} // {{ q.question_type || 'essay' }} // W: {{ q.weight || 0 }}
                                            </p>
                                            <p class="text-[12px] font-sans text-slate-200 break-words">{{ q.question_text }}</p>
                                        </div>
                                        <div class="text-right text-[8px] uppercase">
                                            <span v-if="q.question_type === 'multiple_choice'"
                                                :class="taskBankMcqByQuestion?.[q.uuid]?.is_correct ? 'text-emerald-400' : 'text-red-400'">
                                                {{ taskBankMcqByQuestion?.[q.uuid]?.is_correct ? 'CORRECT' : 'WRONG' }}
                                            </span>
                                            <span v-else-if="q.question_type === 'platforming'" class="text-purple-400">PLATFORMING</span>
                                            <span v-else-if="q.question_type === 'word_match'" class="text-orange-400">WORD_MATCH</span>
                                            <span v-else class="text-yellow-400">ESSAY</span>
                                        </div>
                                    </div>

                                    <div v-if="q.question_type === 'multiple_choice'" class="mt-3 text-[11px] font-sans">
                                        <p class="text-slate-400 uppercase text-[8px] italic">AUTO_POINTS:</p>
                                        <p class="text-cyan-300">
                                            +{{ taskBankMcqByQuestion?.[q.uuid]?.earned_points || 0 }} / {{ q.weight || 0 }}
                                        </p>
                                    </div>

                                    <div v-else class="mt-4">
                                        <label class="block text-[7px] text-slate-500 uppercase italic mb-2">
                                            ESSAY_SCORE (0–{{ q.weight || 0 }}):
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="essayPoints[q.uuid]"
                                            :min="0"
                                            :max="q.weight || 0"
                                            step="1"
                                            @input="validateEssayPoints(q)"
                                            class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                        />
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="hasRubric">
                                <div v-for="criterion in rubricCriteria" :key="criterion.id"
                                    class="p-4 border-2 border-slate-800 bg-black/40 hover:border-cyan-500 transition-all">
                                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="text-[10px] uppercase font-bold text-white break-words">
                                                {{ criterion.name }}
                                            </p>
                                            <p class="text-[7px] text-slate-500 uppercase italic mt-1">
                                                WEIGHT: <span class="text-slate-300 font-bold">{{ Number(criterion.weight || 0).toFixed(2) }}</span>
                                            </p>
                                            <p v-if="rubricCellDescription(criterion.id, selectedLevels[criterion.id])"
                                                class="text-[11px] font-sans text-slate-300 mt-3 leading-relaxed border-l-2 border-slate-700 pl-3 italic">
                                                "{{ rubricCellDescription(criterion.id, selectedLevels[criterion.id]) }}"
                                            </p>
                                        </div>

                                        <div class="shrink-0 w-full md:w-56">
                                            <label class="block text-[7px] text-slate-500 uppercase italic mb-2">SELECT_LEVEL:</label>
                                            <select
                                                v-model.number="selectedLevels[criterion.id]"
                                                class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                            >
                                                <option :value="0">-- SELECT --</option>
                                                <option v-for="lvl in rubricLevels" :key="lvl.id" :value="lvl.id">
                                                    {{ lvl.label }} ({{ Number(lvl.score_value || 0).toFixed(0) }})
                                                </option>
                                            </select>
                                            <p v-if="missingRubricCriteriaIds.includes(criterion.id)"
                                                class="text-[8px] text-red-400 uppercase mt-2">
                                                LEVEL_REQUIRED
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 border-2 border-slate-800 bg-slate-950/30">
                                    <div class="flex justify-between text-[8px] uppercase">
                                        <span class="text-slate-400 font-sans">Max Level Score:</span>
                                        <span class="text-slate-200 font-bold">{{ Number(rubricMaxLevelScore || 0).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">Total Weight:</span>
                                        <span class="text-slate-200 font-bold">{{ Number(rubricMaxWeight || 0).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="p-5 border-2 border-slate-700 bg-gradient-to-br from-[#101722] via-black/70 to-[#111827] shadow-[0_16px_36px_rgba(2,8,16,0.34)] md:p-6">
                                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_240px] xl:items-start">
                                        <div class="space-y-4">
                                            <p class="text-[8px] text-yellow-400 uppercase tracking-widest">>> MANUAL_SCORE_MODE</p>
                                            <p class="text-[7px] text-slate-500 uppercase italic mt-1">
                                                Range: 1–100 (fallback tanpa rubric)
                                            </p>
                                            <div class="space-y-3">
                                                <label for="manual-score-range" class="block text-[7px] text-slate-500 uppercase italic">
                                                    Quick_Adjust
                                                </label>
                                                <input
                                                    id="manual-score-range"
                                                    type="range"
                                                    v-model.number="manualFinalScore"
                                                    min="1"
                                                    max="100"
                                                    step="1"
                                                    @input="validateManualScore"
                                                    class="manual-score-range"
                                                />
                                                <div class="flex items-center justify-between text-[7px] uppercase text-slate-500">
                                                    <span>1</span>
                                                    <span>25</span>
                                                    <span>50</span>
                                                    <span>75</span>
                                                    <span>100</span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                                <button type="button" class="manual-score-step" @click="adjustManualScore(-10)">-10</button>
                                                <button type="button" class="manual-score-step" @click="adjustManualScore(-1)">-1</button>
                                                <button type="button" class="manual-score-step" @click="setManualScore(50)">50</button>
                                                <button type="button" class="manual-score-step" @click="adjustManualScore(1)">+1</button>
                                                <button type="button" class="manual-score-step" @click="adjustManualScore(10)">+10</button>
                                            </div>
                                        </div>
                                        <div class="manual-score-display">
                                            <label for="manual-score-input" class="manual-score-display__label">
                                                Final Score
                                            </label>
                                            <div class="manual-score-display__input-shell">
                                                <input
                                                    id="manual-score-input"
                                                    type="number"
                                                    v-model.number="manualFinalScore"
                                                    min="1"
                                                    max="100"
                                                    step="1"
                                                    inputmode="numeric"
                                                    @input="validateManualScore"
                                                    @blur="validateManualScore"
                                                    class="manual-score-display__input"
                                                />
                                                <span class="manual-score-display__suffix">%</span>
                                            </div>
                                            <p class="manual-score-display__helper">
                                                Ketik langsung atau pakai kontrol cepat di samping.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-5 flex flex-col justify-between space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-black p-4 border-2 border-slate-800 text-center">
                                    <p class="text-[7px] text-slate-600 uppercase mb-2 italic">Final_Score:</p>
                                    <p class="text-2xl font-bold" :class="totalScore >= 70 ? 'text-green-400' : (totalScore >= 40 ? 'text-yellow-400' : 'text-red-500')">
                                        {{ totalScore }}%
                                    </p>
                                </div>
                                <div class="bg-black p-4 border-2 border-slate-800 text-center text-[10px] flex items-center justify-center font-bold uppercase tracking-tighter"
                                    :class="getStatusClass(localStatus)">
                                    {{ localStatus }}
                                </div>
                            </div>

                            <div class="bg-slate-800/30 p-4 border-2 border-slate-700 space-y-2">
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans">Quest Max Reward:</span>
                                    <span class="text-slate-300 font-bold">{{ submission.quest.reward_gold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase border-t border-slate-700 pt-2">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated Gold ({{ totalScore }}%):</span>
                                    <span class="text-yellow-400 font-bold underline">+{{ calculatedGold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated EXP ({{ totalScore }}%):</span>
                                    <span class="text-cyan-400 font-bold">+{{ calculatedExp }} XP</span>
                                </div>
                            </div>

                            <div class="bg-black p-4 border-2 border-slate-800">
                                <label class="block text-[7px] text-slate-500 uppercase italic mb-2">Verdict_Status:</label>
                                <select
                                    v-model="localStatus"
                                    class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                >
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>

                            <textarea v-model="feedbackText"
                                class="w-full bg-black border-2 border-slate-800 p-4 text-slate-200 font-sans text-xs focus:border-green-500 outline-none h-32 custom-scrollbar"
                                placeholder="TYPE_COMMANDER_FEEDBACK_LOG_HERE..."></textarea>

                            <div class="flex flex-col gap-2">
                                <p class="text-[7px] text-slate-500 uppercase italic">Execution_Command:</p>
                                <button
                                    @click="submitEvaluation"
                                    class="w-full py-5 font-bold text-[10px] uppercase tracking-widest transition-all active:translate-y-1 active:shadow-none bg-cyan-700 hover:bg-cyan-600 text-black shadow-[4px_4px_0_0_#0e7490]"
                                >
                                    [ CONFIRM_VERDICT_AND_REWARD ]
                                </button>
                                <p class="text-[6px] text-center text-slate-600 mt-2 italic">
                                    *Caution: This action will modify user balance and experience.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="showAiPreviewModal" class="fixed inset-0 z-[140] bg-black/85 backdrop-blur-sm p-4 flex items-center justify-center">
                <div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto border-2 border-indigo-700 bg-[#0d1117] p-4 md:p-6 space-y-4">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-700 pb-3">
                        <h4 class="text-[10px] text-indigo-300 uppercase">AI_PAYLOAD_PREVIEW</h4>
                        <button
                            type="button"
                            @click="closeAiPreviewModal"
                            class="px-3 py-1 border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white text-[8px] uppercase"
                        >
                            CLOSE
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="border border-slate-800 bg-black/40 p-3">
                            <p class="text-[8px] uppercase text-slate-400 mb-2">RINGKASAN_METADATA</p>
                            <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1 custom-scrollbar">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                        <p class="text-[7px] uppercase text-slate-500">Quest</p>
                                        <p class="text-[11px] text-slate-100 font-semibold break-words">{{ aiPreviewSummary.quest || '-' }}</p>
                                        <p class="text-[8px] uppercase text-indigo-300 mt-1">{{ aiPreviewSummary.difficulty || '-' }}</p>
                                    </div>
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                        <p class="text-[7px] uppercase text-slate-500">Sumber Data</p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span
                                                v-for="flag in aiPreviewSummary.source_flags"
                                                :key="flag"
                                                class="px-2 py-[2px] border border-slate-600 text-[7px] uppercase tracking-wide text-slate-200"
                                            >{{ flag }}</span>
                                            <span
                                                v-if="!aiPreviewSummary.source_flags.length"
                                                class="text-[8px] uppercase text-slate-500"
                                            >-</span>
                                        </div>
                                        <p class="text-[7px] uppercase text-amber-300 mt-2" v-if="aiPreviewSummary.warnings.length">
                                            Warning: {{ aiPreviewSummary.warnings.join(', ') }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-[7px] uppercase text-slate-500 mb-1">Q/A Counter</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                            <p class="text-[7px] uppercase text-slate-500">Total Soal</p>
                                            <p class="text-[16px] font-mono text-cyan-200">{{ aiPreviewSummary.task_bank.question_total ?? '-' }}</p>
                                        </div>
                                        <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                            <p class="text-[7px] uppercase text-slate-500">Terjawab</p>
                                            <p class="text-[16px] font-mono text-emerald-300">{{ aiPreviewSummary.task_bank.answered_total ?? '-' }}</p>
                                        </div>
                                        <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                            <p class="text-[7px] uppercase text-slate-500">Kosong</p>
                                            <p class="text-[16px] font-mono text-rose-300">{{ aiPreviewSummary.task_bank.unanswered_total ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 border border-slate-800 bg-slate-900/40 px-3 py-2">
                                        <div class="flex items-center justify-between text-[8px] uppercase text-slate-400">
                                            <span>Completion</span>
                                            <span class="text-slate-200 font-mono">{{ aiPreviewSummary.task_bank.answer_completion_rate }}%</span>
                                        </div>
                                        <div class="h-2 mt-1 bg-slate-800 overflow-hidden">
                                            <div
                                                class="h-full"
                                                :class="aiPreviewSummary.completion_class"
                                                :style="{ width: aiPreviewSummary.task_bank.answer_completion_rate + '%' }"
                                            ></div>
                                        </div>
                                        <div class="flex flex-wrap gap-2 mt-2 text-[7px] uppercase text-slate-400">
                                            <span>Sumber: <span class="text-slate-200">{{ aiPreviewSummary.task_bank.count_source }}</span></span>
                                            <span v-if="aiPreviewSummary.task_bank.ai_count_confidence">AI Confidence: <span class="text-slate-200">{{ aiPreviewSummary.task_bank.ai_count_confidence }}</span></span>
                                        </div>
                                        <p class="text-[8px] text-slate-300 mt-2 leading-relaxed" v-if="aiPreviewSummary.task_bank.ai_count_notes">
                                            {{ aiPreviewSummary.task_bank.ai_count_notes }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                        <p class="text-[7px] uppercase text-slate-500">Artifact</p>
                                        <p class="text-[9px] text-slate-200">Raw: {{ aiPreviewSummary.artifact.raw_combined_chars }} chars</p>
                                        <p class="text-[9px] text-slate-200">Normalized: {{ aiPreviewSummary.artifact.normalized_chars }} chars</p>
                                        <p
                                            class="text-[8px] uppercase mt-1"
                                            :class="aiPreviewSummary.artifact.is_truncated ? 'text-amber-300' : 'text-emerald-300'"
                                        >
                                            {{ aiPreviewSummary.artifact.is_truncated ? 'Truncated' : 'Complete' }}
                                        </p>
                                    </div>
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                        <p class="text-[7px] uppercase text-slate-500">Rubric</p>
                                        <p class="text-[9px] text-slate-200">
                                            <span :class="aiPreviewSummary.rubric.present ? 'text-emerald-300' : 'text-rose-300'">
                                                {{ aiPreviewSummary.rubric.present ? 'Tersedia' : 'Tidak ada' }}
                                            </span>
                                        </p>
                                        <p class="text-[9px] text-slate-200">Kriteria: {{ aiPreviewSummary.rubric.criteria_total }}</p>
                                        <p class="text-[9px] text-slate-200">Level: {{ aiPreviewSummary.rubric.levels_total }}</p>
                                        <p class="text-[9px] text-slate-200">Matrix: {{ aiPreviewSummary.rubric.matrix_entries_total }}</p>
                                    </div>
                                </div>

                                <div class="border border-slate-800 bg-slate-900/40 px-3 py-2">
                                    <div class="flex items-center justify-between text-[8px] uppercase text-slate-400">
                                        <span>Evidence Quality</span>
                                        <span class="text-slate-200 font-mono">{{ aiPreviewSummary.evidence.quality_score }}</span>
                                    </div>
                                    <div class="h-2 mt-1 bg-slate-800 overflow-hidden">
                                        <div
                                            class="h-full"
                                            :class="aiPreviewSummary.quality_class"
                                            :style="{ width: aiPreviewSummary.quality_percent + '%' }"
                                        ></div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 mt-2 text-[8px] uppercase text-slate-400">
                                        <span>Chunks: <span class="text-slate-200">{{ aiPreviewSummary.evidence.chunk_count }}</span></span>
                                        <span>Rubric ev: <span class="text-slate-200">{{ aiPreviewSummary.evidence.rubric_evidence_count }}</span></span>
                                        <span>TaskBank ev: <span class="text-slate-200">{{ aiPreviewSummary.evidence.task_bank_evidence_count }}</span></span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2">
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2 text-center">
                                        <p class="text-[7px] uppercase text-slate-500">Conf. Overall</p>
                                        <p class="text-[14px] font-mono text-cyan-200">{{ aiPreviewSummary.confidence.overall }}</p>
                                    </div>
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2 text-center">
                                        <p class="text-[7px] uppercase text-slate-500">Conf. Rubric</p>
                                        <p class="text-[14px] font-mono text-indigo-200">{{ aiPreviewSummary.confidence.rubric }}</p>
                                    </div>
                                    <div class="border border-slate-800 bg-slate-900/40 px-3 py-2 text-center">
                                        <p class="text-[7px] uppercase text-slate-500">Conf. TaskBank</p>
                                        <p class="text-[14px] font-mono text-emerald-200">{{ aiPreviewSummary.confidence.task_bank }}</p>
                                    </div>
                                </div>

                                <div class="text-[8px] uppercase text-slate-400">
                                    Advisor Note:
                                    <span
                                        :class="aiPreviewSummary.advisor_note_present ? 'text-emerald-300' : 'text-slate-500'"
                                    >
                                        {{ aiPreviewSummary.advisor_note_present ? 'Ada catatan reviewer' : 'Belum ada catatan' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="border border-slate-800 bg-black/40 p-3">
                            <p class="text-[8px] uppercase text-slate-400 mb-2">DETAIL_JSON</p>
                            <pre class="text-[10px] font-sans text-slate-300 whitespace-pre-wrap break-words max-h-72 overflow-y-auto custom-scrollbar">{{ aiPreviewJsonText }}</pre>
                        </div>
                    </div>

                    <div class="border border-amber-700 bg-amber-950/20 p-3 space-y-2">
                        <p class="text-[8px] uppercase text-amber-300">CATATAN_KE_AI (opsional)</p>
                        <textarea
                            v-model="aiAdvisorNote"
                            rows="4"
                            class="w-full bg-black border border-slate-700 p-3 font-sans text-[12px] text-slate-100"
                            placeholder="Contoh: fokuskan penilaian pada bukti implementasi route + controller, abaikan typo minor."
                        />
                        <p class="text-[7px] text-slate-500 uppercase">Catatan ini akan ditambahkan ke prompt final sebelum request AI.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <button
                            type="button"
                            @click="confirmAiScanFromPreview"
                            :disabled="isScanning"
                            class="px-4 py-2 border-2 border-indigo-500 text-indigo-300 hover:bg-indigo-500 hover:text-black text-[8px] uppercase"
                        >
                            {{ isScanning ? 'ANALYZING...' : 'KIRIM_KE_AI' }}
                        </button>
                        <button
                            type="button"
                            @click="closeAiPreviewModal"
                            class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white text-[8px] uppercase"
                        >
                            BATAL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    submission: Object,
    rubric: Object,
    rubricSource: String,
    aiAdvisorConfig: {
        type: Object,
        default: () => ({ auto_apply_min_confidence: 65 }),
    },
});

// 1. STATE & DATA
const feedbackText = ref(props.submission.feedback || '');
const localStatus = ref(['Approved', 'Rejected'].includes(props.submission.status) ? props.submission.status : 'Approved');
const isScanning = ref(false);
const isPreparingAiPreview = ref(false);
const showAiPreviewModal = ref(false);
const aiPreviewPayload = ref(null);
const aiAdvisorNote = ref('');
const manualFinalScore = ref(Math.max(1, Math.min(100, Number(props.submission.grade || 1))));
const selectedLevels = ref({});
const essayPoints = ref({});
const taskAnswers = computed(() => {
    const answers = props.submission?.scores_detail?.answers;
    return answers && typeof answers === 'object' ? answers : {};
});
const taskBank = computed(() => props.submission?.quest?.task_bank || null);
const taskQuestions = computed(() => {
    const questions = taskBank.value?.questions;
    return Array.isArray(questions) ? questions : [];
});
const isTaskBankSubmission = computed(() => taskQuestions.value.length > 0);
const taskBankType = computed(() => taskBank.value?.assessment_type || null);
const mcqQuestions = computed(() => taskQuestions.value.filter((q) => q.question_type === 'multiple_choice'));
const essayQuestions = computed(() => taskQuestions.value.filter((q) => q.question_type !== 'multiple_choice'));
const aiPreviewJsonText = computed(() => {
    if (!aiPreviewPayload.value) return '{}';
    try {
        return JSON.stringify(aiPreviewPayload.value, null, 2);
    } catch (error) {
        return '{}';
    }
});
const aiPreviewSummaryText = computed(() => {
    const preview = aiPreviewPayload.value;
    if (!preview) return 'Belum ada metadata preview.';

    const quest = preview?.quest || {};
    const artifact = preview?.artifact || {};
    const evidence = preview?.evidence || {};
    const confidence = evidence?.confidence || {};
    const stats = preview?.stats || {};
    const artifactStats = stats?.artifact || {};
    const taskBankStats = stats?.task_bank || {};
    const rubricStats = stats?.rubric || {};
    const evidenceStats = stats?.evidence || {};
    const confidenceStats = stats?.confidence || confidence || {};
    const advisorNotePresent = Boolean(stats?.advisor_note_present);
    const sourceFlags = Array.isArray(artifact?.source_flags) ? artifact.source_flags.join(', ') : '-';
    const warnings = Array.isArray(artifact?.warnings) ? artifact.warnings.join(', ') : '-';

    return [
        `Quest: ${String(quest?.title || '-')}`,
        `Difficulty: ${String(quest?.difficulty || '-')}`,
        `Source Flags: ${sourceFlags || '-'}`,
        `Artifact Warnings: ${warnings || '-'}`,
        `Raw Artifact Chars: ${String(artifactStats?.raw_combined_chars ?? '-')}`,
        `Normalized Chars: ${String(artifactStats?.normalized_chars ?? '-')}`,
        `Artifact Truncated: ${String(Boolean(artifactStats?.is_truncated))}`,
        `TaskBank Present: ${String(Boolean(taskBankStats?.present))}`,
        `Question Total: ${String(taskBankStats?.question_total ?? '-')}`,
        `Answered Total: ${String(taskBankStats?.answered_total ?? '-')}`,
        `Unanswered Total: ${String(taskBankStats?.unanswered_total ?? '-')}`,
        `Answer Completion Rate: ${String(taskBankStats?.answer_completion_rate ?? '-')}%`,
        `Count Source: ${String(taskBankStats?.count_source ?? '-')}`,
        `AI Count Confidence: ${String(taskBankStats?.ai_count_confidence ?? '-')}`,
        `AI Count Notes: ${String(taskBankStats?.ai_count_notes ?? '-')}`,
        `Rubric Present: ${String(Boolean(rubricStats?.present))}`,
        `Rubric Criteria Total: ${String(rubricStats?.criteria_total ?? '-')}`,
        `Rubric Levels Total: ${String(rubricStats?.levels_total ?? '-')}`,
        `Rubric Matrix Entries: ${String(rubricStats?.matrix_entries_total ?? '-')}`,
        `Quality Score: ${String(evidenceStats?.quality_score ?? evidence?.quality_score ?? '-')}`,
        `Evidence Chunk Count: ${String(evidenceStats?.chunk_count ?? '-')}`,
        `Rubric Evidence Count: ${String(evidenceStats?.rubric_evidence_count ?? '-')}`,
        `TaskBank Evidence Count: ${String(evidenceStats?.task_bank_evidence_count ?? '-')}`,
        `Confidence Overall: ${String(confidenceStats?.overall ?? '-')}`,
        `Confidence Rubric: ${String(confidenceStats?.rubric ?? '-')}`,
        `Confidence TaskBank: ${String(confidenceStats?.task_bank ?? '-')}`,
        `Advisor Note Present: ${String(advisorNotePresent)}`,
    ].join('\n');
});

const aiPreviewSummary = computed(() => {
    const preview = aiPreviewPayload.value || {};
    const quest = preview?.quest || {};
    const artifact = preview?.artifact || {};
    const evidence = preview?.evidence || {};
    const stats = preview?.stats || {};
    const taskBankStats = stats?.task_bank || {};
    const artifactStats = stats?.artifact || {};
    const rubricStats = stats?.rubric || {};
    const evidenceStats = stats?.evidence || {};
    const confidenceStats = stats?.confidence || evidence?.confidence || {};

    const completionRate = Math.max(0, Math.min(100, Number(taskBankStats?.answer_completion_rate ?? 0) || 0));
    const completionClass = completionRate >= 90
        ? 'bg-emerald-400'
        : completionRate >= 60
            ? 'bg-amber-400'
            : 'bg-rose-500';

    const qualityScoreRaw = Number(evidenceStats?.quality_score ?? evidence?.quality_score ?? 0) || 0;
    const qualityPercent = qualityScoreRaw <= 1 ? Math.round(qualityScoreRaw * 100) : Math.round(qualityScoreRaw);
    const qualityClass = qualityPercent >= 70
        ? 'bg-emerald-400'
        : qualityPercent >= 40
            ? 'bg-amber-400'
            : 'bg-rose-500';

    return {
        quest: String(quest?.title || ''),
        difficulty: String(quest?.difficulty || ''),
        source_flags: Array.isArray(artifact?.source_flags) ? artifact.source_flags : [],
        warnings: Array.isArray(artifact?.warnings) ? artifact.warnings : [],
        artifact: {
            raw_combined_chars: Number(artifactStats?.raw_combined_chars ?? 0) || 0,
            normalized_chars: Number(artifactStats?.normalized_chars ?? 0) || 0,
            is_truncated: Boolean(artifactStats?.is_truncated),
        },
        task_bank: {
            question_total: taskBankStats?.question_total ?? 0,
            answered_total: taskBankStats?.answered_total ?? 0,
            unanswered_total: taskBankStats?.unanswered_total ?? 0,
            answer_completion_rate: completionRate,
            count_source: String(taskBankStats?.count_source ?? '-'),
            ai_count_confidence: Number(taskBankStats?.ai_count_confidence ?? 0) || 0,
            ai_count_notes: String(taskBankStats?.ai_count_notes ?? ''),
        },
        rubric: {
            present: Boolean(rubricStats?.present),
            criteria_total: Number(rubricStats?.criteria_total ?? 0) || 0,
            levels_total: Number(rubricStats?.levels_total ?? 0) || 0,
            matrix_entries_total: Number(rubricStats?.matrix_entries_total ?? 0) || 0,
        },
        evidence: {
            quality_score: qualityScoreRaw,
            chunk_count: Number(evidenceStats?.chunk_count ?? 0) || 0,
            rubric_evidence_count: Number(evidenceStats?.rubric_evidence_count ?? 0) || 0,
            task_bank_evidence_count: Number(evidenceStats?.task_bank_evidence_count ?? 0) || 0,
        },
        confidence: {
            overall: Number(confidenceStats?.overall ?? 0) || 0,
            rubric: Number(confidenceStats?.rubric ?? 0) || 0,
            task_bank: Number(confidenceStats?.task_bank ?? 0) || 0,
        },
        advisor_note_present: Boolean(stats?.advisor_note_present),
        completion_class: completionClass,
        quality_class: qualityClass,
        quality_percent: qualityPercent,
    };
});

const attachmentPreviewUrl = computed(() => {
    if (!props.submission?.file_path) return null;
    try {
        return route('admin.submissions.file', { submission: props.submission.uuid });
    } catch (error) {
        // Fallback untuk deploy yang cache Ziggy-nya belum update.
        return `/submissions/${props.submission.uuid}/file`;
    }
});

// 2. COMPUTED PROPERTIES
const hasRubric = computed(() => !!props.rubric?.rubric?.id);
const rubricTitle = computed(() => props.rubric?.rubric?.title || 'N/A');
const rubricSourceLabel = computed(() => {
    const src = String(props.rubricSource || '').toLowerCase();
    if (src === 'quest') return 'quest';
    if (src === 'task_bank') return 'task_bank';
    return '';
});
const rubricCriteria = computed(() => Array.isArray(props.rubric?.criteria) ? props.rubric.criteria : []);
const rubricLevels = computed(() => Array.isArray(props.rubric?.levels) ? props.rubric.levels : []);
const rubricMaxLevelScore = computed(() => {
    const scores = rubricLevels.value.map((l) => Number(l.score_value || 0));
    return scores.length ? Math.max(...scores) : 0;
});
const rubricMaxWeight = computed(() => rubricCriteria.value.reduce((acc, c) => acc + Number(c.weight || 0), 0));
const missingRubricCriteriaIds = computed(() => {
    if (!hasRubric.value) return [];
    return rubricCriteria.value
        .filter((c) => Number(selectedLevels.value?.[c.id] || 0) <= 0)
        .map((c) => c.id);
});

const rubricTotalScore = computed(() => {
    if (!hasRubric.value) return 0;
    const maxLevel = Number(rubricMaxLevelScore.value || 0);
    const maxWeight = Number(rubricMaxWeight.value || 0);
    if (maxLevel <= 0 || maxWeight <= 0) return 0;

    let total = 0;
    rubricCriteria.value.forEach((c) => {
        const levelId = Number(selectedLevels.value?.[c.id] || 0);
        const level = rubricLevels.value.find((l) => Number(l.id) === levelId);
        const selectedScore = level ? Number(level.score_value || 0) : 0;
        const weight = Number(c.weight || 0);
        total += (selectedScore / maxLevel) * weight;
    });

    const percent = Math.round((total / maxWeight) * 100);
    return Math.max(0, Math.min(100, percent));
});

const taskBankMcqByQuestion = computed(() => {
    const by = {};
    mcqQuestions.value.forEach((q) => {
        const uuid = String(q.uuid || '');
        const weight = Math.max(0, Number(q.weight || 0));
        const selected = String(selectedAnswerFor(q) || '');
        const answerKey = String(q.answer_key || '');
        const isCorrect = selected !== '' && answerKey !== '' && selected === answerKey;
        by[uuid] = {
            weight,
            selected,
            answer_key: answerKey,
            is_correct: isCorrect,
            earned_points: isCorrect ? weight : 0,
        };
    });
    return by;
});

const taskBankMcqMaxPoints = computed(() => Object.values(taskBankMcqByQuestion.value).reduce((acc, v) => acc + Number(v.weight || 0), 0));
const taskBankMcqEarnedPoints = computed(() => Object.values(taskBankMcqByQuestion.value).reduce((acc, v) => acc + Number(v.earned_points || 0), 0));
const taskBankEssayMaxPoints = computed(() => essayQuestions.value.reduce((acc, q) => acc + Math.max(0, Number(q.weight || 0)), 0));
const taskBankEssayEarnedPoints = computed(() => {
    return essayQuestions.value.reduce((acc, q) => {
        const uuid = String(q.uuid || '');
        const max = Math.max(0, Number(q.weight || 0));
        const raw = Number(essayPoints.value?.[uuid] ?? 0);
        const clamped = Math.max(0, Math.min(max, isNaN(raw) ? 0 : raw));
        return acc + clamped;
    }, 0);
});
const taskBankMaxPoints = computed(() => taskQuestions.value.reduce((acc, q) => acc + Math.max(0, Number(q.weight || 0)), 0));
const taskBankEarnedPoints = computed(() => Number(taskBankMcqEarnedPoints.value || 0) + Number(taskBankEssayEarnedPoints.value || 0));
const taskBankTotalScore = computed(() => {
    const max = Number(taskBankMaxPoints.value || 0);
    if (max <= 0) return 0;
    const percent = Math.round((Number(taskBankEarnedPoints.value || 0) / max) * 100);
    return Math.max(0, Math.min(100, percent));
});

const totalScore = computed(() => {
    if (isTaskBankSubmission.value) return taskBankTotalScore.value;
    if (hasRubric.value) return rubricTotalScore.value;
    return Math.max(1, Math.min(100, Number(manualFinalScore.value || 1)));
});

const calculatedGold = computed(() => {
    const baseGold = Number(props.submission.quest.reward_gold) || 0;
    return Math.floor(baseGold * (totalScore.value / 100));
});

const calculatedExp = computed(() => {
    const baseExp = maxExpReward.value;
    return Math.floor(baseExp * (totalScore.value / 100));
});

const maxExpReward = computed(() => {
    const exp = Number(props.submission.quest.reward_exp) || 0;
    if (exp > 0) return exp;
    const gold = Number(props.submission.quest.reward_gold) || 0;
    return gold > 0 ? gold : 1000;
});

// 3. METHODS
const validateManualScore = () => {
    const raw = Number(manualFinalScore.value || 1);
    if (isNaN(raw)) {
        manualFinalScore.value = 1;
        return;
    }
    manualFinalScore.value = Math.max(1, Math.min(100, Math.round(raw)));
};

const setManualScore = (value) => {
    manualFinalScore.value = Number(value || 1);
    validateManualScore();
};

const adjustManualScore = (delta) => {
    setManualScore(Number(manualFinalScore.value || 1) + Number(delta || 0));
};

const validateEssayPoints = (question) => {
    const uuid = String(question?.uuid || '');
    if (!uuid) return;
    const max = Math.max(0, Number(question?.weight || 0));
    const raw = Number(essayPoints.value?.[uuid] ?? 0);
    const clamped = Math.max(0, Math.min(max, isNaN(raw) ? 0 : raw));
    essayPoints.value[uuid] = clamped;
};

const getStatusClass = (status) => {
    if (status === 'Approved') return 'text-green-400 border-green-500 bg-green-500/10';
    if (status === 'Rejected') return 'text-red-500 border-red-500 bg-red-500/10';
    return 'text-yellow-500 border-yellow-500 bg-yellow-500/10';
};

const isImage = (path) => /\.(jpg|jpeg|png|webp|avif|gif)$/i.test(path);
const isPdf = (path) => /\.pdf$/i.test(path);
const isMobilePdfPreview = computed(() => {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator?.userAgent || '';
    const iPadOs = /Macintosh/i.test(ua) && (window.navigator?.maxTouchPoints || 0) > 1;
    return /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(ua) || iPadOs;
});

const rubricCellDescription = (criteriaId, levelId) => {
    if (!hasRubric.value) return '';
    const cid = Number(criteriaId || 0);
    const lid = Number(levelId || 0);
    if (!cid || !lid) return '';
    return props.rubric?.matrix?.[cid]?.[lid] || '';
};

const selectedAnswerFor = (question) => {
    const uuid = String(question?.uuid || '');
    if (!uuid) return '';
    const value = taskAnswers.value?.[uuid];
    return typeof value === 'string' ? value : String(value || '');
};

const parsedGameAnswer = (question) => {
    const raw = selectedAnswerFor(question);
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
};

const parsedWordMatchAnswer = (question) => {
    const raw = selectedAnswerFor(question);
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
};

const applyEssayScoresFromAi = (essayScores) => {
    if (!isTaskBankSubmission.value || !Array.isArray(essayScores) || essayScores.length === 0) {
        return 0;
    }

    const questionMap = new Map(
        essayQuestions.value.map((question) => [String(question.uuid || ''), Number(question.weight || 0)])
    );
    const next = { ...essayPoints.value };
    let appliedCount = 0;

    essayScores.forEach((item) => {
        if (!item || typeof item !== 'object') return;
        const uuid = String(item.question_uuid || item.uuid || '').trim();
        if (!uuid || !questionMap.has(uuid)) return;

        const maxScore = Number(item.max_score ?? questionMap.get(uuid) ?? 0);
        const rawScore = Number(item.score ?? 0);
        const clamped = Math.max(0, Math.min(Number.isFinite(maxScore) && maxScore > 0 ? maxScore : questionMap.get(uuid) || 0, Number.isFinite(rawScore) ? rawScore : 0));

        next[uuid] = Math.round(clamped);
        appliedCount += 1;
    });

    if (appliedCount > 0) {
        essayPoints.value = next;
    }

    return appliedCount;
};

onMounted(() => {
    const savedScores = props.submission.scores_detail;

    if (hasRubric.value) {
        const verdict = savedScores && typeof savedScores === 'object'
            ? (savedScores.verdict && typeof savedScores.verdict === 'object' ? savedScores.verdict : null)
            : null;

        if (verdict && verdict.source === 'rubric' && Number(verdict.rubric_id) === Number(props.rubric.rubric.id)) {
            selectedLevels.value = { ...(verdict.selected_level_by_criteria_id || {}) };
        } else {
            const initial = {};
            rubricCriteria.value.forEach((c) => { initial[c.id] = 0; });
            selectedLevels.value = initial;
        }
        return;
    }

    if (isTaskBankSubmission.value) {
        const verdictEssay = savedScores?.verdict?.task_bank?.essay?.by_question || {};
        const initial = {};
        essayQuestions.value.forEach((q) => {
            const uuid = String(q.uuid || '');
            const saved = verdictEssay?.[uuid]?.earned_points;
            initial[uuid] = typeof saved === 'number' ? saved : Number(saved || 0);
        });
        essayPoints.value = initial;
        return;
    }
});

const closeAiPreviewModal = () => {
    showAiPreviewModal.value = false;
};

const scanWithAI = async () => {
    isPreparingAiPreview.value = true;
    try {
        const response = await axios.post(route('admin.submissions.checkAIPreview', { submission: props.submission.uuid }), {
            advisor_note: aiAdvisorNote.value,
        });

        aiPreviewPayload.value = response?.data?.preview || null;
        showAiPreviewModal.value = true;
    } catch (error) {
        const backendMessage = String(error?.response?.data?.message || '').trim();
        Swal.fire('UPLINK_ERROR', backendMessage || 'AI preview unavailable.', 'error');
    } finally {
        isPreparingAiPreview.value = false;
    }
};

const confirmAiScanFromPreview = async () => {
    isScanning.value = true;
    try {
        const response = await axios.post(route('admin.submissions.checkAI', { submission: props.submission.uuid }), {
            advisor_note: aiAdvisorNote.value,
        });
        const data = response.data;
        const minAutoApplyConfidence = Math.max(1, Math.min(100, Number(props.aiAdvisorConfig?.auto_apply_min_confidence ?? 65)));

        const applyRubricRecommendations = (recommendations) => {
            if (!hasRubric.value || !Array.isArray(recommendations) || !recommendations.length) {
                return 0;
            }

            const nextSelectedLevels = { ...(selectedLevels.value || {}) };
            let appliedCount = 0;

            recommendations.forEach((item) => {
                let criteria = null;
                const criteriaId = Number(item?.criteria_id || 0);
                if (criteriaId > 0) {
                    criteria = rubricCriteria.value.find((c) => Number(c.id) === criteriaId) || null;
                }

                if (!criteria) {
                    const criteriaName = String(item?.criteria_name || '').trim().toLowerCase();
                    if (criteriaName) {
                        criteria = rubricCriteria.value.find((c) => String(c.name || '').trim().toLowerCase() === criteriaName) || null;
                    }
                }

                if (!criteria) return;

                let level = null;
                const suggestedLevelId = Number(item?.suggested_level_id || 0);
                if (suggestedLevelId > 0) {
                    level = rubricLevels.value.find((l) => Number(l.id) === suggestedLevelId) || null;
                }

                if (!level) {
                    const suggestedLevelNumber = Number(item?.suggested_level || item?.level || 0);
                    if (suggestedLevelNumber > 0) {
                        level = rubricLevels.value.find((l) => Number(l.level) === suggestedLevelNumber) || null;
                    }
                }

                if (!level) {
                    const suggestedLevelLabel = String(item?.suggested_level_label || item?.level_label || '').trim().toLowerCase();
                    if (suggestedLevelLabel) {
                        level = rubricLevels.value.find((l) => String(l.label || '').trim().toLowerCase() === suggestedLevelLabel) || null;
                    }
                }

                if (!level) return;

                const current = Number(nextSelectedLevels[criteria.id] || 0);
                const next = Number(level.id || 0);
                if (next > 0 && current !== next) {
                    nextSelectedLevels[criteria.id] = next;
                    appliedCount += 1;
                }
            });

            if (appliedCount > 0) {
                selectedLevels.value = nextSelectedLevels;
            }

            return appliedCount;
        };

        const autoFillRubricByScore = (targetScore) => {
            if (!hasRubric.value || !Number.isFinite(targetScore) || targetScore <= 0) {
                return 0;
            }

            const levels = [...rubricLevels.value]
                .filter((item) => Number(item?.id || 0) > 0)
                .sort((a, b) => Number(a?.score_value || 0) - Number(b?.score_value || 0));

            if (!levels.length) return 0;

            const maxScoreValue = Math.max(...levels.map((item) => Number(item?.score_value || 0)));
            if (maxScoreValue <= 0) return 0;

            let nearestLevel = levels[0];
            let nearestDiff = Number.POSITIVE_INFINITY;

            levels.forEach((levelItem) => {
                const ratio = (Number(levelItem?.score_value || 0) / maxScoreValue) * 100;
                const diff = Math.abs(ratio - targetScore);
                if (diff < nearestDiff) {
                    nearestDiff = diff;
                    nearestLevel = levelItem;
                }
            });

            const nextSelectedLevels = { ...(selectedLevels.value || {}) };
            let appliedCount = 0;

            rubricCriteria.value.forEach((criterion) => {
                const criterionId = Number(criterion?.id || 0);
                if (criterionId <= 0) return;

                const current = Number(nextSelectedLevels[criterionId] || 0);
                const next = Number(nearestLevel?.id || 0);
                if (next > 0 && current !== next) {
                    nextSelectedLevels[criterionId] = next;
                    appliedCount += 1;
                }
            });

            if (appliedCount > 0) {
                selectedLevels.value = nextSelectedLevels;
            }

            return appliedCount;
        };

        let suggested = 0;
        const scoreRange = String(data.suggested_score_range || '').trim();
        const match = scoreRange.match(/^(\d{1,3})\s*-\s*(\d{1,3})$/);
        if (match) {
            const min = Math.max(1, Math.min(100, Number(match[1])));
            const max = Math.max(1, Math.min(100, Number(match[2])));
            suggested = Math.round((min + max) / 2);
        } else {
            const func = Number(data.func ?? 0);
            const logic = Number(data.logic ?? 0);
            const clean = Number(data.clean ?? 0);
            suggested = Math.round((func + logic + clean) / 3);
        }

        const confidenceOverall = Number(data?.confidence?.overall ?? 0);
        const evidenceQualityScore = Number(data?.evidence_quality_score ?? 0);
        const qualityWarnings = Array.isArray(data?.evidence_quality_warnings) ? data.evidence_quality_warnings : [];

        if (!hasRubric.value && !isTaskBankSubmission.value && !isNaN(suggested) && suggested > 0) {
            manualFinalScore.value = Math.max(1, Math.min(100, suggested));
        }

        let appliedRubricCount = applyRubricRecommendations(data.rubric_recommendations);
        if (
            hasRubric.value
            && appliedRubricCount === 0
            && confidenceOverall >= minAutoApplyConfidence
            && !isNaN(suggested)
            && suggested > 0
        ) {
            appliedRubricCount = autoFillRubricByScore(suggested);
        }

        const appliedEssayCount = applyEssayScoresFromAi(data.essay_scores);

        const suggestionText = String(data.suggested_feedback || data.feedback || '').trim();
        const summaryText = String(data.summary || '').trim();
        const advisoryText = [summaryText, suggestionText].filter(Boolean).join(' | ');
        feedbackText.value = `[AI_ADVISOR]: ${advisoryText}\n\n${feedbackText.value}`;

        const lowConfidenceMessage = confidenceOverall > 0 && confidenceOverall < minAutoApplyConfidence
            ? `Low confidence (${confidenceOverall}). Review manual wajib.`
            : '';

        const qualityWarningMessage = qualityWarnings.length
            ? `Warnings: ${qualityWarnings.join(', ')}`
            : '';

        const messageParts = [
            appliedRubricCount > 0
                ? `System calibrated. ${appliedRubricCount} rubric level auto-selected.`
                : 'System has been calibrated with AI suggestions.',
            appliedEssayCount > 0 ? `Essay: ${appliedEssayCount} skor diisi otomatis.` : '',
            `Confidence: ${confidenceOverall || 0}`,
            `Evidence quality: ${evidenceQualityScore || 0}`,
            lowConfidenceMessage,
            qualityWarningMessage,
        ].filter(Boolean);

        showAiPreviewModal.value = false;

        Swal.fire({
            title: 'AI_ANALYSIS_COMPLETE',
            text: messageParts.join(' | '),
            icon: 'success',
            background: '#0d1117',
            color: '#4ed4d4',
            confirmButtonColor: '#1e293b'
        });
    } catch (error) {
        const backendMessage = String(error?.response?.data?.message || '').trim();
        const fallbackMessage = 'AI Advisor is currently offline.';
        Swal.fire('UPLINK_ERROR', backendMessage || fallbackMessage, 'error');
    } finally {
        isScanning.value = false;
    }
};

const submitEvaluation = () => {
    const status = localStatus.value;

    if (hasRubric.value && missingRubricCriteriaIds.value.length > 0) {
        Swal.fire({
            title: 'RUBRIC_INCOMPLETE',
            text: 'Pilih level untuk semua kriteria sebelum menyimpan verdict.',
            icon: 'warning',
            background: '#0d1117',
            color: '#4ed4d4',
            confirmButtonColor: '#a16207',
        });
        return;
    }

    if (!hasRubric.value && !isTaskBankSubmission.value) {
        validateManualScore();
    }

    Swal.fire({
        title: `CONFIRM ${status.toUpperCase()}?`,
        html: `
            <div class="text-left font-mono text-[10px] space-y-2 bg-black p-4 border border-slate-700 mt-4">
                <p class="text-cyan-400">FINAL_GRADE: ${totalScore.value}%</p>
                <p class="text-yellow-400">GOLD_EARNED: +${calculatedGold.value} G</p>
                <p class="text-blue-400">EXP_GAINED: +${calculatedExp.value} XP</p>
                <p class="text-white mt-2 border-t border-slate-800 pt-2">STATUS: ${status}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '[ EXECUTE_VERDICT ]',
        cancelButtonText: '[ ABORT ]',
        background: '#0d1117',
        color: '#4ed4d4',
        confirmButtonColor: '#0891b2',
        cancelButtonColor: '#1e293b',
    }).then((result) => {
        if (result.isConfirmed) {
            const payload = isTaskBankSubmission.value
                ? {
                    status,
                    feedback: feedbackText.value,
                    question_points: { ...essayPoints.value },
                }
                : (hasRubric.value
                    ? {
                        status,
                        feedback: feedbackText.value,
                        selected_levels: { ...selectedLevels.value },
                    }
                    : {
                        final_score: manualFinalScore.value,
                        feedback: feedbackText.value,
                        status: status,
                    });

            router.post(route('admin.submissions.verdict', { submission: props.submission.uuid }), payload, {
                onSuccess: () => {
                    Swal.fire({
                        title: 'SYSTEM_UPDATED',
                        text: 'Verdict has been permanently logged.',
                        icon: 'success',
                        background: '#0d1117',
                        color: '#4ed4d4'
                    });
                },
            });
        }
    });
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}

/* Chrome, Safari, Edge, Opera - Remove arrows on number input */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}

.manual-score-range {
    width: 100%;
    height: 1rem;
    appearance: none;
    background: linear-gradient(90deg, rgba(234, 179, 8, 0.18), rgba(34, 211, 238, 0.28));
    border: 1px solid rgba(71, 85, 105, 0.9);
    border-radius: 9999px;
    outline: none;
}

.manual-score-range::-webkit-slider-thumb {
    appearance: none;
    width: 1.25rem;
    height: 1.25rem;
    background: #22d3ee;
    border: 2px solid #082f49;
    border-radius: 9999px;
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.16);
    cursor: pointer;
}

.manual-score-range::-moz-range-thumb {
    width: 1.25rem;
    height: 1.25rem;
    background: #22d3ee;
    border: 2px solid #082f49;
    border-radius: 9999px;
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.16);
    cursor: pointer;
}

.manual-score-step {
    @apply border border-cyan-900/80 bg-slate-950/80 px-3 py-3 text-[9px] uppercase text-cyan-200 transition-all hover:border-cyan-400 hover:bg-cyan-400 hover:text-black active:translate-y-[1px];
}

.manual-score-display {
    @apply flex flex-col justify-center gap-3 border-2 border-cyan-400/20 bg-slate-950/80 p-4 md:p-5;
}

.manual-score-display__label {
    @apply text-[7px] uppercase tracking-[0.22em] text-cyan-300/70;
}

.manual-score-display__input-shell {
    @apply flex items-center border-2 border-cyan-400/30 bg-[#0f172a] px-4 py-3 shadow-[0_0_0_1px_rgba(34,211,238,0.08)];
}

.manual-score-display__input {
    @apply min-w-0 flex-1 bg-transparent text-right font-mono text-[28px] text-cyan-300 outline-none md:text-[34px];
}

.manual-score-display__suffix {
    @apply pl-3 font-mono text-[18px] text-cyan-100 md:text-[22px];
}

.manual-score-display__helper {
    @apply font-sans text-[11px] leading-relaxed text-slate-400;
}
</style>
