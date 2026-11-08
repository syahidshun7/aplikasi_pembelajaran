<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminSidebar from './AdminSidebar.vue';
import RedesignTopbar from './RedesignTopbar.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    shopOverview: { type: Object, default: () => ({}) },
    jobCommandItems: { type: Array, default: () => [] },
    redesignAttention: { type: Object, default: () => ({}) },
    dashboardInsights: { type: Object, default: () => ({}) },
});

const number = value => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
const days = computed(() => props.dashboardInsights.days || []);
const chartMax = computed(() => Math.max(1, ...days.value.map(day => Number(day.submitted) || 0)));
const barHeight = value => `${Math.max(0, (Number(value) || 0) / chartMax.value * 100)}%`;
const reviewStatuses = computed(() => [
    { key: 'approved', label: 'Disetujui', count: Number(props.dashboardInsights.statuses?.approved) || 0 },
    { key: 'rejected', label: 'Ditolak', count: Number(props.dashboardInsights.statuses?.rejected) || 0 },
    { key: 'pending', label: 'Menunggu', count: Number(props.dashboardInsights.statuses?.pending) || 0 },
]);
const statusTotal = computed(() => reviewStatuses.value.reduce((total, status) => total + status.count, 0));
const statusPercent = count => statusTotal.value ? Math.round(count / statusTotal.value * 100) : 0;
const metrics = computed(() => [
    { label: 'Peserta', value: number(props.stats.total_students), note: `+${number(props.dashboardInsights.new_participants_14d)} dalam 14 hari`, href: route('redesign.users') },
    { label: 'Study groups', value: number(props.stats.total_study_groups), note: `${number(props.stats.total_jobs)} program`, href: route('redesign.study-groups') },
    { label: 'Review menunggu', value: number(props.stats.pending_verdicts), note: `${number(props.stats.graded_7d)} dinilai dalam 7 hari`, href: route('redesign.submissions'), attention: true },
    { label: 'Rata-rata nilai', value: Number(props.stats.graded_30d) ? number(props.stats.avg_grade_30d) : '—', note: `${number(props.stats.graded_30d)} dinilai dalam 30 hari`, href: route('redesign.submissions') },
]);
const previewJobs = computed(() => props.jobCommandItems.slice(0, 4));
const recentTransactions = computed(() => props.shopOverview?.recent_transactions || []);
const transactionDate = value => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : '—';
</script>

<template>
    <Head title="Dashboard admin" />
    <div class="dashboard-app">
        <AdminSidebar />
        <main class="dashboard-main">
            <RedesignTopbar breadcrumb="Overview" />
            <div class="dashboard-content">
                <header class="page-heading">
                    <div>
                        <h1>Dashboard admin</h1>
                        <p>Pantau aktivitas belajar, penilaian, dan program dalam satu tampilan.</p>
                    </div>
                    <div class="health" :class="`health-${stats.system_health || 'healthy'}`">
                        <span class="health-dot" aria-hidden="true"></span>
                        <span>System {{ stats.system_health || 'healthy' }}</span>
                        <small v-if="stats.system_errors_24h !== undefined">{{ number(stats.system_errors_24h) }} error / 24 jam</small>
                    </div>
                </header>

                <section class="metric-grid" aria-label="Angka utama">
                    <Link v-for="metric in metrics" :key="metric.label" :href="metric.href" class="metric" :class="{ 'metric-attention': metric.attention }">
                        <span class="metric-label">{{ metric.label }}</span>
                        <strong>{{ metric.value }}</strong>
                        <span class="metric-note">{{ metric.note }}</span>
                    </Link>
                </section>

                <div class="insights-grid">
                    <section class="panel activity-panel" aria-labelledby="activity-title">
                        <div class="section-heading">
                            <div>
                                <h2 id="activity-title">Aktivitas submission</h2>
                                <p>Jumlah tugas yang dikirim setiap hari dalam 14 hari terakhir.</p>
                            </div>
                            <div class="activity-totals"><strong>{{ number(dashboardInsights.submitted_14d) }}</strong><span>submission baru</span></div>
                        </div>
                        <div class="chart-legend"><span><i class="legend-submitted"></i>Dikirim</span><span><i class="legend-reviewed"></i>Sudah dinilai</span></div>
                        <div v-if="dashboardInsights.submitted_14d" class="chart-scroll">
                            <div class="bar-chart" role="img" :aria-label="`Grafik 14 hari: ${number(dashboardInsights.submitted_14d)} submission dikirim, ${number(dashboardInsights.reviewed_14d)} sudah dinilai`">
                                <div v-for="day in days" :key="day.date" class="chart-day" :title="`${day.label}: ${number(day.submitted)} dikirim, ${number(day.reviewed)} sudah dinilai`">
                                    <div class="chart-bars"><span class="bar-submitted" :style="{ height: barHeight(day.submitted) }"></span><span class="bar-reviewed" :style="{ height: barHeight(day.reviewed) }"></span></div>
                                    <span class="chart-date">{{ day.label }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="chart-empty">Belum ada submission dalam 14 hari terakhir.</div>
                        <p class="chart-footnote">“Sudah dinilai” menghitung submission dalam periode ini yang saat ini berstatus disetujui atau ditolak.</p>
                    </section>

                    <section class="panel status-panel" aria-labelledby="status-title">
                        <div class="section-heading"><div><h2 id="status-title">Status penilaian</h2><p>Seluruh submission yang tercatat.</p></div></div>
                        <strong class="status-total">{{ number(statusTotal) }}<span> total</span></strong>
                        <div v-if="statusTotal" class="status-bar" role="img" :aria-label="reviewStatuses.map(status => `${status.label} ${number(status.count)}`).join(', ')">
                            <span v-for="status in reviewStatuses" :key="status.key" :class="`status-${status.key}`" :style="{ width: `${status.count / statusTotal * 100}%` }"></span>
                        </div>
                        <div v-else class="status-bar status-bar-empty" aria-hidden="true"></div>
                        <div class="status-list">
                            <div v-for="status in reviewStatuses" :key="status.key"><span><i :class="`status-${status.key}`"></i>{{ status.label }}</span><strong>{{ number(status.count) }}</strong><small>{{ statusPercent(status.count) }}%</small></div>
                        </div>
                    </section>
                </div>

                <div class="operations-grid">
                    <section class="panel jobs-panel" aria-labelledby="jobs-title">
                        <div class="section-heading"><div><h2 id="jobs-title">Job command</h2><p>Masuk ke program untuk mengelola kelas dan bank tugas.</p></div><Link class="section-link" :href="route('redesign.jobs')">Semua program →</Link></div>
                        <div v-if="previewJobs.length" class="job-list">
                            <Link v-for="job in previewJobs" :key="job.id" :href="route('redesign.jobs.show', job.id)" class="job-row"><span><strong>{{ job.name }}</strong><small>{{ number(job.study_groups_count) }} kelas · {{ number(job.users_count) }} peserta · {{ number(job.task_banks_count) }} bank</small></span><em :class="`job-${job.status}`">{{ job.status === 'active' ? 'Aktif' : job.status === 'coming_soon' ? 'Segera hadir' : 'Draft' }}</em></Link>
                        </div>
                        <p v-else class="empty-message">Belum ada program. Tambahkan program untuk memulai.</p>
                    </section>
                    <section class="panel attention-panel" aria-labelledby="attention-title">
                        <div class="section-heading"><div><h2 id="attention-title">Perlu perhatian</h2><p>Hal yang menunggu tindakan operator.</p></div></div>
                        <Link :href="route('redesign.submissions')"><span>Submission menunggu</span><strong>{{ number(stats.pending_verdicts) }}</strong></Link>
                        <Link :href="route('redesign.study-groups', { view: 'pending' })"><span>Permintaan gabung</span><strong>{{ number(redesignAttention.pending_join_requests) }}</strong></Link>
                        <Link :href="route('redesign.creations.queue')"><span>Karya perlu direview</span><strong>{{ number(redesignAttention.pending_creations) }}</strong></Link>
                    </section>
                </div>

                <section class="panel performance" aria-labelledby="shop-activity-title">
                    <div class="section-heading"><div><h2 id="shop-activity-title">Aktivitas toko</h2><p>Saldo gold peserta saat ini dan delapan transaksi toko terbaru.</p></div><Link class="section-link" :href="route('redesign.shop-items')">Kelola toko →</Link></div>
                    <div class="economy-summary">
                        <div><span>Gold beredar</span><strong>{{ number(shopOverview.gold_in_circulation) }}</strong><small>Total saldo akun peserta</small></div>
                        <div><span>Gold diperoleh toko</span><strong>{{ number(shopOverview.shop_gold_earned) }}</strong><small>Total pembelian setelah pengembalian</small></div>
                    </div>
                    <div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Peserta</th><th>Item</th><th>Aktivitas</th><th>Jumlah</th><th>Perubahan gold</th></tr></thead><tbody><tr v-for="transaction in recentTransactions" :key="transaction.id"><td>{{ transactionDate(transaction.created_at) }}</td><td><Link :href="route('redesign.users.ledger', transaction.user_id)">{{ transaction.user_name }}</Link><small class="table-secondary">@{{ transaction.username }}</small></td><td><Link v-if="transaction.item_id" :href="route('redesign.shop-items.detail', transaction.item_id)">{{ transaction.item_name }}</Link><span v-else>{{ transaction.item_name }}</span></td><td><span class="transaction-type" :class="{ 'transaction-cancelled': transaction.cancelled }">{{ transaction.cancelled ? 'Dibatalkan' : transaction.type === 'purchase' ? 'Pembelian' : 'Pemakaian' }}</span></td><td>{{ number(transaction.quantity) }}</td><td :class="{ 'gold-out': transaction.gold_change < 0 && !transaction.cancelled }">{{ transaction.cancelled ? 'Dikembalikan' : transaction.gold_change ? number(transaction.gold_change) : '—' }}</td></tr><tr v-if="!recentTransactions.length"><td colspan="6" class="empty-message">Belum ada transaksi toko.</td></tr></tbody></table></div>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.dashboard-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.dashboard-main{flex:1;min-width:0}.dashboard-content{max-width:1440px;margin:auto;padding:34px 30px 60px}.page-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:26px}.page-heading h1{font-size:30px;line-height:1.2;letter-spacing:-.025em;margin:0;font-weight:750}.page-heading p,.section-heading p{color:#667f87;font-size:12px;line-height:1.5;margin:7px 0 0}.health{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#007d72;font-size:11px;font-weight:700;text-transform:capitalize;white-space:nowrap}.health small{border-left:1px solid #dce6e8;padding-left:9px;color:#667f87;font-weight:500}.health-dot{width:7px;height:7px;border-radius:50%;background:#00a88b}.health-warning{color:#9d6515}.health-warning .health-dot{background:#d99a32}.health-critical{color:#ba414a}.health-critical .health-dot{background:#d34d58}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.metric{display:flex;flex-direction:column;min-height:136px;padding:20px;background:#fff;border:1px solid #dce6e8;border-radius:12px;color:#19333d;text-decoration:none;transition:border-color .18s ease,background .18s ease}.metric:hover{border-color:#85bbb5;background:#fbfefd}.metric-label{font-size:12px;color:#647d84}.metric strong{font-size:32px;line-height:1.1;letter-spacing:-.025em;font-weight:750;margin:17px 0 9px;font-variant-numeric:tabular-nums}.metric-note{font-size:11px;color:#667f87;margin-top:auto}.metric-attention strong{color:#ae4a43}.insights-grid{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(285px,1fr);gap:16px;margin-top:18px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:22px;min-width:0}.section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.section-heading h2{font-size:17px;line-height:1.25;letter-spacing:-.015em;margin:0;font-weight:750}.activity-totals{text-align:right;white-space:nowrap}.activity-totals strong{display:block;font-size:23px;line-height:1;font-variant-numeric:tabular-nums}.activity-totals span{display:block;color:#667f87;font-size:10px;margin-top:5px}.chart-legend{display:flex;gap:16px;margin-top:23px;font-size:11px;color:#506b72}.chart-legend span{display:flex;align-items:center;gap:7px}.chart-legend i,.status-list i{width:9px;height:9px;border-radius:2px;display:inline-block}.legend-submitted{background:#008d83}.legend-reviewed{background:#93d4ca}.chart-scroll{overflow-x:auto;margin-top:16px}.bar-chart{display:grid;grid-template-columns:repeat(14,minmax(32px,1fr));gap:5px;min-width:510px;height:181px;border-bottom:1px solid #dce6e8}.chart-day{display:flex;flex-direction:column;align-items:center;min-width:0}.chart-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px;width:100%;height:150px}.chart-bars span{display:block;width:10px;max-height:100%;border-radius:3px 3px 0 0}.bar-submitted{background:#008d83}.bar-reviewed{background:#93d4ca}.chart-date{font-size:9px;color:#667f87;white-space:nowrap;padding-top:8px}.chart-empty{display:grid;place-items:center;height:181px;color:#667f87;font-size:12px}.chart-footnote{font-size:10px;color:#667f87;line-height:1.5;margin:17px 0 0}.status-total{display:block;font-size:31px;line-height:1;margin:34px 0 20px;font-weight:750;font-variant-numeric:tabular-nums}.status-total span{font-size:12px;font-weight:500;color:#667f87}.status-bar{display:flex;height:15px;overflow:hidden;border-radius:4px;background:#edf3f4}.status-bar span{height:100%}.status-approved{background:#008d83}.status-rejected{background:#d67870}.status-pending{background:#e8ad58}.status-bar-empty{background:#edf3f4}.status-list{display:grid;gap:14px;margin-top:24px}.status-list>div{display:grid;grid-template-columns:minmax(0,1fr) auto 38px;align-items:center;gap:10px;font-size:11px}.status-list span{display:flex;align-items:center;gap:9px;color:#506b72}.status-list strong,.status-list small{text-align:right;font-variant-numeric:tabular-nums}.status-list small{color:#667f87}.operations-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,1fr);gap:16px;margin-top:18px}.section-link{color:#007f78;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap}.section-link:hover{text-decoration:underline;text-underline-offset:3px}.job-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:20px}.job-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;min-width:0;padding:16px;background:#f6f9f9;border:1px solid #e2ebed;border-radius:8px;color:#19333d;text-decoration:none}.job-row:hover{background:#eff8f6;border-color:#a6ceca}.job-row strong{display:block;font-size:13px;line-height:1.3;overflow-wrap:anywhere}.job-row small{display:block;color:#667f87;font-size:10px;line-height:1.4;margin-top:7px}.job-row em{font-style:normal;font-size:10px;font-weight:700;color:#007d72;background:#e3f3ef;border-radius:5px;padding:5px 7px;white-space:nowrap}.job-row em.job-coming_soon{color:#9d6515;background:#fff2d8}.job-row em.job-draft{color:#536d74;background:#e8eef0}.attention-panel{display:flex;flex-direction:column}.attention-panel .section-heading{margin-bottom:12px}.attention-panel>a{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #e5edef;color:#19333d;text-decoration:none;font-size:12px}.attention-panel>a:last-child{border-bottom:0}.attention-panel>a:hover span{color:#007f78;text-decoration:underline}.attention-panel>a strong{color:#007f78;font-size:15px;font-variant-numeric:tabular-nums}.attention-panel>a:first-of-type strong{color:#ae4a43}.performance{margin-top:18px}.performance .section-heading{margin-bottom:20px}.table-scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:620px;font-size:12px}th{text-align:left;background:#eff4f5;color:#607a82;padding:12px 14px;font-weight:700}td{padding:14px;border-bottom:1px solid #e5edef}tbody tr:nth-child(even){background:#fafcfc}td a{color:#19333d;font-weight:700;text-decoration:underline;text-decoration-color:#a4cdca;text-underline-offset:3px}td a:hover{color:#007f78}td:nth-child(n+2){font-variant-numeric:tabular-nums}.student-status{color:#007f78;font-weight:600}.student-status.needs-help{color:#a6631a}.empty-message{color:#667f87;font-size:12px;padding:22px;text-align:center}.student-pages{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:5px;margin-top:16px}.student-pages a{border:1px solid #dce6e8;border-radius:5px;padding:7px 10px;color:#007f78;font-size:11px;text-decoration:none}.student-pages a.current{background:#007f78;color:#fff}.student-pages a.disabled{opacity:.45;pointer-events:none}.dashboard-app :is(a,button):focus-visible{outline:2px solid #007f78;outline-offset:3px}@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.insights-grid,.operations-grid{grid-template-columns:1fr}}@media(max-width:800px){.dashboard-app{display:block}.dashboard-content{padding:26px 18px 50px}}@media(max-width:600px){.dashboard-content{padding:22px 14px 45px}.page-heading{flex-direction:column;gap:14px}.page-heading h1{font-size:25px}.metric-grid{gap:9px}.metric{padding:15px;min-height:120px}.metric strong{font-size:26px}.panel{padding:17px}.job-list{grid-template-columns:1fr}.section-heading{flex-wrap:wrap}}
.economy-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0}.economy-summary>div{display:flex;flex-direction:column;gap:7px;padding:17px 19px;background:#f6f9f9;border:1px solid #e2ebed;border-radius:9px}.economy-summary span{font-size:11px;color:#607a82}.economy-summary strong{font-size:25px;line-height:1.1;color:#19333d;font-weight:750;font-variant-numeric:tabular-nums}.economy-summary small,.table-secondary{font-size:10px;color:#70858c}.table-secondary{display:block;margin-top:4px}.transaction-type{display:inline-block;padding:5px 8px;border-radius:5px;background:#e5f5f1;color:#007f78;font-size:10px;font-weight:700}.transaction-cancelled{background:#fff2d8;color:#9d6515}.gold-out{color:#ae4a43;font-weight:700}@media(max-width:600px){.economy-summary{grid-template-columns:1fr}}
</style>
