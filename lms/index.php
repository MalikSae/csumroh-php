<?php
$pageTitle = 'LMS Belajar Mandiri CS - CS Umroh Copilot';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../data/scripts_loader.php';

$conversionCycle = get_conversion_cycle();
?>

<div class="max-w-7xl mx-auto space-y-6" x-data="lmsApp()">

    <!-- LMS Top Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="/csumroh/index.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Kembali ke Copilot</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black">Pusat Belajar Mandiri CS</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">LMS SOP & Alur Konversi Umroh</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Panduan resmi Customer Service menguasai alur percakapan, kualifikasi kebutuhan, dan closing syar'i</p>
        </div>

        <!-- Overall Progress Box -->
        <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3.5 w-full md:w-64 flex flex-col justify-between">
            <div class="flex items-center justify-between text-xs font-semibold text-zinc-800 mb-1.5">
                <span>Progress Belajar:</span>
                <span class="font-mono text-black" x-text="completedCount + ' / ' + modules.length + ' Selesai'"></span>
            </div>
            <!-- Progress Bar (Monochrome) -->
            <div class="w-full h-2 bg-zinc-200 rounded-full overflow-hidden">
                <div class="h-full bg-black transition-all duration-300" :style="'width: ' + progressPercent + '%'"></div>
            </div>
        </div>
    </div>

    <!-- Mobile Module Navigator Bar (lg:hidden) -->
    <div class="lg:hidden bg-white border border-zinc-200/80 rounded-2xl p-3.5 shadow-xs space-y-2.5">
        <div class="flex items-center justify-between text-xs">
            <span class="font-bold text-black uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Pilih Modul:
            </span>
            <span class="font-mono text-[11px] text-zinc-500 font-semibold" x-text="(currentModuleIndex + 1) + ' dari ' + modules.length"></span>
        </div>
        
        <!-- Mobile Swipeable Horizontal Module Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar flex-nowrap pb-1">
            <template x-for="(m, idx) in modules" :key="m.id">
                <button type="button" @click="activeModuleId = m.id"
                        class="min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-semibold shrink-0 whitespace-nowrap transition flex items-center gap-1.5"
                        :class="activeModuleId === m.id ? 'bg-black text-white shadow-xs' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'">
                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px]"
                          :class="isCompleted(m.id) ? (activeModuleId === m.id ? 'bg-white text-black' : 'bg-black text-white') : (activeModuleId === m.id ? 'bg-zinc-700 text-white' : 'bg-zinc-300 text-zinc-700')"
                          x-text="isCompleted(m.id) ? '✓' : (idx + 1)"></span>
                    <span x-text="'Modul ' + (idx + 1)"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Main Course Layout: Sidebar Kurikulum + Kanvas Pelajaran -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- ============================================================== -->
        <!-- SIDEBAR KURIKULUM (Left 4 cols on lg, hidden on mobile)       -->
        <!-- ============================================================== -->
        <div class="hidden lg:block lg:col-span-4 bg-white border border-zinc-200 rounded-xl p-4 shadow-sm sticky top-20">
            <h2 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3 px-1">
                Daftar Modul Pembelajaran
            </h2>

            <div class="space-y-1">
                <template x-for="(m, idx) in modules" :key="m.id">
                    <button type="button" @click="activeModuleId = m.id"
                        class="w-full text-left p-3 rounded-lg text-xs transition flex items-start gap-2.5 border"
                        :class="activeModuleId === m.id 
                                ? 'bg-black text-white border-black shadow-sm' 
                                : 'bg-white border-zinc-200 text-zinc-800 hover:border-black hover:bg-zinc-50'">
                        
                        <!-- Checkmark / Number -->
                        <div class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5 border"
                             :class="isCompleted(m.id) 
                                     ? (activeModuleId === m.id ? 'bg-white text-black border-white' : 'bg-black text-white border-black')
                                     : (activeModuleId === m.id ? 'border-zinc-500 text-zinc-300' : 'border-zinc-300 text-zinc-600')">
                            <span x-show="isCompleted(m.id)">✓</span>
                            <span x-show="!isCompleted(m.id)" x-text="idx + 1"></span>
                        </div>

                        <!-- Title & Meta -->
                        <div class="flex-1 min-w-0">
                            <div class="font-bold truncate" x-text="m.title"></div>
                            <div class="text-[10px] mt-0.5 flex items-center gap-2"
                                 :class="activeModuleId === m.id ? 'text-zinc-300' : 'text-zinc-500'">
                                <span x-text="m.read_time"></span>
                                <span>&bull;</span>
                                <span x-text="m.badge"></span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- KANVAS PELAJARAN UTAMA (Right 8 cols on lg)                    -->
        <!-- ============================================================== -->
        <div class="lg:col-span-8 space-y-6" x-show="currentModule">
            
            <!-- Module Header Card -->
            <div class="bg-white border border-zinc-200 rounded-xl p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <span class="text-[10px] font-mono uppercase bg-zinc-100 text-zinc-800 px-2.5 py-1 rounded font-semibold border border-zinc-200"
                          x-text="'Modul ' + (currentModuleIndex + 1) + ' dari ' + modules.length"></span>
                    <span class="text-xs text-zinc-500 font-medium" x-text="'Estimasi Waktu: ' + currentModule.read_time"></span>
                </div>

                <h2 class="text-2xl font-bold text-black tracking-tight" x-text="currentModule.title"></h2>
                <p class="text-sm text-zinc-600 mt-1 font-medium" x-text="currentModule.subtitle"></p>

                <!-- Objective Box -->
                <div class="mt-4 p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-xs text-zinc-700">
                    <div class="flex items-center gap-1.5 mb-1 text-black font-bold uppercase tracking-wider text-[10px]">
                        <svg class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <circle cx="12" cy="12" r="6"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                        </svg>
                        <span>Target Penguasaan CS:</span>
                    </div>
                    <p class="leading-relaxed text-zinc-600" x-text="currentModule.objective"></p>
                </div>
            </div>

            <!-- Core Conceptual Content -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-sm space-y-5">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider border-b border-zinc-100 pb-2">
                    1. Konsep Inti & Framework Mental
                </h3>

                <div class="prose prose-zinc max-w-none text-xs text-zinc-700 leading-relaxed space-y-3">
                    <template x-for="(point, idx) in currentModule.key_points" :key="idx">
                        <div class="flex items-start gap-3 p-3.5 bg-zinc-50 border border-zinc-200/80 rounded-xl">
                            <span class="w-5 h-5 bg-black text-white rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5" x-text="idx + 1"></span>
                            <div>
                                <h4 class="font-bold text-black text-xs" x-text="point.headline"></h4>
                                <p class="text-zinc-600 mt-0.5 leading-relaxed" x-text="point.explanation"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Principles & Indikator Selesai -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider border-b border-zinc-100 pb-2">
                    2. Prinsip & Indikator Kapan Boleh Pindah Tahap
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-4 border border-zinc-200/80 rounded-xl bg-zinc-50">
                        <div class="flex items-center gap-1.5 mb-2 text-black font-bold uppercase tracking-wider text-[10px]">
                            <svg class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
                            </svg>
                            <span>Prinsip Utama CS:</span>
                        </div>
                        <ul class="space-y-1.5 list-disc list-inside text-zinc-600">
                            <template x-for="(pr, idx) in currentModule.principles" :key="idx">
                                <li x-text="pr"></li>
                            </template>
                        </ul>
                    </div>

                    <div class="p-4 border border-zinc-800 rounded-xl bg-white">
                        <div class="flex items-center gap-1.5 mb-2 text-black font-bold uppercase tracking-wider text-[10px]">
                            <svg class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                <line x1="4" y1="22" x2="4" y2="15"></line>
                            </svg>
                            <span>Indikator Tahap Ini Tuntas:</span>
                        </div>
                        <p class="text-zinc-800 font-medium leading-relaxed" x-text="currentModule.indicator"></p>
                    </div>
                </div>
            </div>

            <!-- Do vs Don't Real Chat Study Case -->
            <div class="bg-white border border-zinc-200 rounded-xl p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider border-b border-zinc-100 pb-2">
                    3. Studi Kasus Percakapan: Do vs Don't
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    
                    <!-- BAD EXAMPLE (DON'T) -->
                    <div class="border border-rose-200 rounded-xl p-4 bg-rose-50/40 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 text-rose-800 font-bold mb-2">
                                <span class="w-4 h-4 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center text-[10px] font-bold">✕</span>
                                <span class="uppercase tracking-wider text-[10px]">Contoh Salah (Kebiasaan Buruk CS)</span>
                            </div>
                            <div class="p-3 bg-white border border-rose-200 rounded-lg text-rose-950 font-mono text-[11px] leading-relaxed whitespace-pre-wrap"
                                 x-text="currentModule.bad_case.chat">
                            </div>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-rose-100 text-[11px] text-rose-900">
                            <strong class="text-rose-800">Kenapa Salah:</strong> <span x-text="currentModule.bad_case.why_bad"></span>
                        </div>
                    </div>

                    <!-- GOOD EXAMPLE (DO) -->
                    <div class="border border-emerald-200 rounded-xl p-4 bg-emerald-50/40 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 text-emerald-800 font-bold mb-2">
                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold">✓</span>
                                <span class="uppercase tracking-wider text-[10px]">Contoh Benar (Standar SOP CS)</span>
                            </div>
                            <div class="p-3 bg-white border border-emerald-200 rounded-lg text-emerald-950 text-[11px] leading-relaxed whitespace-pre-wrap font-sans"
                                 x-text="currentModule.good_case.chat">
                            </div>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-emerald-100 text-[11px] text-emerald-900 font-medium">
                            <strong class="text-emerald-800">Kelebihan:</strong> <span x-text="currentModule.good_case.why_good"></span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Self-Checklist & Navigation Buttons -->
            <div class="bg-white border border-zinc-200 rounded-xl p-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <input type="checkbox" :id="'check-' + currentModule.id" 
                           :checked="isCompleted(currentModule.id)"
                           @change="toggleCompleted(currentModule.id)"
                           class="w-4 h-4 text-emerald-600 border-zinc-300 rounded focus:ring-emerald-500 cursor-pointer">
                    <label :for="'check-' + currentModule.id" class="text-xs font-semibold text-zinc-800 cursor-pointer select-none">
                        Saya sudah memahami dan siap mempraktikkan modul ini
                    </label>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" @click="prevModule()" :disabled="currentModuleIndex === 0"
                        class="px-3.5 py-2 border border-zinc-300 rounded-md text-xs font-semibold text-zinc-700 hover:border-black disabled:opacity-30 disabled:pointer-events-none transition inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        <span>Sebelumnya</span>
                    </button>
                    <button type="button" @click="nextModule()"
                        class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-md text-xs font-semibold transition shadow-sm inline-flex items-center gap-1.5">
                        <span x-text="currentModuleIndex === modules.length - 1 ? 'Selesai Seluruh Modul' : 'Modul Berikutnya'"></span>
                        <template x-if="currentModuleIndex < modules.length - 1">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </template>
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- LMS Data & Alpine Application Script -->
<script>
function lmsApp() {
    return {
        activeModuleId: 'modul-1',
        completedModules: JSON.parse(localStorage.getItem('csumroh_completed_modules') || '[]'),

        modules: [
            {
                id: 'modul-1',
                title: 'Pengantar Umroh Conversion Cycle',
                subtitle: 'Filosofi 6 Tahap Konversi & Mindset CS sebagai Konsultan Ibadah',
                read_time: '4 Menit Baca',
                badge: 'Konsep Dasar',
                objective: 'Memahami siklus utuh percakapan calon jamaah agar tidak ada chat yang menggantung tanpa kejelasan status akhir.',
                key_points: [
                    {
                        headline: '6 Tahap Berurutan (Jangan Lompat)',
                        explanation: 'Greeting -> Identifikasi -> Penawaran -> Closing -> Keberatan (TGJP) -> Follow-up. Jangan langsung melempar harga dan brosur sebelum tahu kebutuhan jamaah.'
                    },
                    {
                        headline: 'Mindset: CS adalah Pendamping Ibadah',
                        explanation: 'Tugas CS bukan semata-mata menjual tiket, melainkan memfasilitasi niat suci tamu Allah menuju Baitullah dengan rasa tenang, amanah, dan terjamin.'
                    },
                    {
                        headline: 'Prinsip Zero-Hanging Lead',
                        explanation: 'Setiap obrolan wajib memiliki kesimpulan status yang jelas: lanjut kualifikasi, penawaran, closing DP, nurture (persiapan), atau batal dengan alasan jelas.'
                    }
                ],
                principles: [
                    'Dengarkan lebih banyak daripada berbicara.',
                    'Gunakan bahasa sopan, hangat, dan bernuansa silaturahmi.',
                    'Haram hukumnya menjanjikan fasilitas atau jadwal palsu demi closing cepat.'
                ],
                indicator: 'CS paham urutan 6 tahap dan tidak lagi langsung membombardir brosur pada pesan pertama.',
                bad_case: {
                    chat: 'Jamaah: "Halo mau tanya umroh dong kak"\nCS: "(Langsung kirim 6 gambar brosur + daftar harga 10 paket tanpa salam)"',
                    why_bad: 'Calon jamaah merasa overwhelmed, bingung memilih, dan merasa sedang berhadapan dengan robot/spammer.'
                },
                good_case: {
                    chat: 'Jamaah: "Halo mau tanya umroh dong kak"\nCS: "Wa\'alaikumussalam Warahmatullah Kak. Salam kenal, saya Fitri dari tim layanan jamaah resmi Azhan Tour. InsyaAllah siap mendampingi. Kalau boleh tahu, Kakak sedang mencari rencana keberangkatan untuk bulan apa ya Kak?"',
                    why_good: 'Menyambut hangat, memvalidasi identitas resmi travel, dan memberikan 1 pertanyaan pembuka yang sangat mudah dijawab.'
                }
            },
            {
                id: 'modul-2',
                title: 'Stage 1: Greeting + Sell Yourself',
                subtitle: 'Membangun Rasa Aman & Kepercayaan dalam 3 Detik Pertama',
                read_time: '3 Menit Baca',
                badge: 'Tahap 1',
                objective: 'Membuka percakapan dengan elegan, menyebut nama jamaah, dan memposisikan diri sebagai tim representatif resmi travel yang siap membantu.',
                key_points: [
                    {
                        headline: 'Sambut Sesuai Konteks Pesan Pertama',
                        explanation: 'Jika prospek datang dari iklan web promo, sapa sesuai promo tersebut. Jika bertanya jadwal, langsung arahkan ke pilihan jadwal.'
                    },
                    {
                        headline: 'Perkenalkan Diri & Brand Resmi',
                        explanation: 'Jamaah masa kini sangat berhati-hati terhadap travel bodong. Sebutkan nama Anda dan nama brand resmi sejak salam pertama.'
                    },
                    {
                        headline: 'Akhiri dengan 1 Pertanyaan Ringan',
                        explanation: 'Tutup salam dengan 1 pertanyaan terarah untuk menggiring percakapan ke Tahap Identifikasi (misal: "Rencana berangkat kapan Kak?").'
                    }
                ],
                principles: [
                    'Jangan buat salam pembuka terlalu panjang hingga menyerupai koran.',
                    'Sebut nama calon jamaah jika sudah diketahui.',
                    'Jika jamaah sudah menyebut kebutuhan di awal, jangan menanyakan ulang hal yang sama.'
                ],
                indicator: 'Calon jamaah merespons dengan nyaman dan bersedia membagikan rencananya.',
                bad_case: {
                    chat: 'CS: "Selamat datang di travel kami. Kami adalah travel resmi berizin Kemenag sejak tahun 2010 dengan ribuan jamaah. Kami menyediakan paket reguler, VIP, plus Turki, plus Dubai, plus Mesir. Silakan dipilih bapak mau yang mana."',
                    why_bad: 'Terlalu kaku, ego-sentris (fokus ke perusahaan bukan ke jamaah), dan membingungkan.'
                },
                good_case: {
                    chat: 'CS: "Assalamu\'alaikum Ibu Fatimah. Terima kasih sudah menghubungi kami. Saya Fitri dari layanan jamaah Azhan Tour. InsyaAllah saya siap bantu carikan pilihan terbaik untuk keberangkatan Ibu sekeluarga. Rencananya ada target di bulan apa Bu?"',
                    why_good: 'Menyapa personal dengan nama, memperkenalkan diri, dan langsung mengajukan 1 pertanyaan pembuka.'
                }
            },
            {
                id: 'modul-3',
                title: 'Stage 2: Prospect Identification (NPGD)',
                subtitle: 'Memahami Kebutuhan Nyata Calon Jamaah Sebelum Memberikan Solusi',
                read_time: '5 Menit Baca',
                badge: 'Tahap 2 & NPGD',
                objective: 'Menguasai 4 unsur framework NPGD untuk membedakan apa yang dicari jamaah dengan apa yang sebenarnya mereka khawatirkan.',
                key_points: [
                    {
                        headline: 'N - Need (Kebutuhan Nyata)',
                        explanation: 'Bulan keberangkatan, jumlah orang yang berangkat, kota asal, dan budget estimasi.'
                    },
                    {
                        headline: 'P - Pain (Kekhawatiran / Hambatan)',
                        explanation: 'Orang tua tidak kuat jalan jauh, takut visa tidak keluar, trauma janji manis travel lain, atau takut makanan tidak cocok.'
                    },
                    {
                        headline: 'G - Gain (Manfaat Idaman)',
                        explanation: 'Ingin hotel dekat pelataran masjid, ingin muthawwif yang sabar membimbing lansia, ingin penerbangan langsung tanpa transit.'
                    },
                    {
                        headline: 'D - Dream (Pengalaman Spiritual Impian)',
                        explanation: 'Ingin bisa khusyuk berdoa di Raudhah, ingin membahagiakan ibu sebelum wafat, atau umroh pertama bersama anak.'
                    }
                ],
                principles: [
                    'Identifikasi dilakukan lewat obrolan santai, bukan interogasi kaku seperti mengisi formulir sensus.',
                    'Cukup gali 2-3 poin yang paling relevan dengan kondisi jamaah.',
                    'Tahan diri untuk tidak langsung merekomendasikan paket sebelum tahu Pain & Gain mereka.'
                ],
                indicator: 'CS memiliki gambaran jelas mengenai jadwal, peserta, dan prioritas jamaah (jarak hotel / harga / kenyamanan).',
                bad_case: {
                    chat: 'Jamaah: "Ada paket umroh buat akhir tahun?"\nCS: "Ada kak, 35 juta bintang 5, 28 juta bintang 3. Mau yang mana?"',
                    why_bad: 'Tidak menggali siapa yang akan berangkat. Jika ternyata membawa nenek 80 tahun dan memilih bintang 3 yang jauh, jamaah akan tersiksa.'
                },
                good_case: {
                    chat: 'Jamaah: "Ada paket umroh buat akhir tahun?"\nCS: "Ada Bu. Untuk akhir tahun kami ada beberapa pilihan jadwal. Supaya pas rekomendasinya, nanti rencana berangkat sendiri atau bersama keluarga? Apakah ada anggota lansia atau anak kecil yang ikut serta Bu?"',
                    why_good: 'Menggali Pain potensial (lansia/anak) yang nantinya menjadi alasan terkuat mengapa paket tertentu lebih disarankan.'
                }
            },
            {
                id: 'modul-4',
                title: 'Stage 3: Crafting Offers (MRBVA Formula)',
                subtitle: 'Menyajikan Rekomendasi Paket dengan Alasan yang Relevan',
                read_time: '5 Menit Baca',
                badge: 'Tahap 3 & MRBVA',
                objective: 'Memberikan penawaran paket umroh yang menjawab masalah jamaah dengan formula Match - Reason - Benefit - Value - Action.',
                key_points: [
                    {
                        headline: 'M - Match (Kecocokan Paket)',
                        explanation: 'Pilih maksimal 1 paket utama dan 1 alternatif yang paling selaras dengan hasil kualifikasi NPGD.'
                    },
                    {
                        headline: 'R - Reason (Alasan Rekomendasi)',
                        explanation: 'Sampaikan mengapa paket ini yang Anda pilihkan untuk mereka, bukan paket yang lain.'
                    },
                    {
                        headline: 'B - Benefit (Sorot Manfaat Utama)',
                        explanation: 'Hubungkan fitur paket dengan kebutuhan mereka (misal: "Karena bawa orang tua, hotel depan pelataran akan sangat meringankan beliau").'
                    },
                    {
                        headline: 'V - Value (Harga & Kepastian Fasilitas)',
                        explanation: 'Sampaikan harga secara transparan dan jelaskan apa saja yang sudah termasuk.'
                    },
                    {
                        headline: 'A - Action (Arahkan ke Keputusan)',
                        explanation: 'Tutup penawaran dengan ajakan langkah berikutnya (misal: cek ketersediaan tanggal atau seat).'
                    }
                ],
                principles: [
                    'Mulai dari kebutuhan jamaah, bukan dari fitur brosur.',
                    'Harga disampaikan dengan lugas tanpa ada biaya tersembunyi.',
                    'Maksimal sodorkan 2 opsi paket agar jamaah tidak mengalami paradox of choice.'
                ],
                indicator: 'Jamaah memahami mengapa paket tersebut cocok untuk dirinya dan merespons positif terhadap nilai penawaran.',
                bad_case: {
                    chat: 'CS: "Ini rinciannya ya kak: Hotel Retaj, Pesawat Lion, 28 juta, manasik 1x, free koper, makan 3x."',
                    why_bad: 'Hanya menyebutkan fitur teknis kering tanpa menyentuh alasan dan manfaat emosional jamaah.'
                },
                good_case: {
                    chat: 'CS: "Melihat rencana Ibu yang berangkat bersama ibunda tercinta, saya sangat menyarankan Paket VIP 12 Hari kami Bu. Kenapa? Karena hotel di Makkah persis di Pullman Zamzam depan pelataran, jadi ibunda tidak perlu jalan jauh atau naik shuttle. Harganya di 36,9 jt dengan fasilitas VIP dan penerbangan langsung Saudia tanpa transit. Kalau pilihan ini cocok dengan rencana Ibu, saya bisa bantu amankan seat untuk tanggal 25 Desember ya Bu?"',
                    why_good: 'Lengkap menerapkan Match, Reason, Benefit, Value, dan Action dalam 1 pesan yang padat dan menyentuh hati.'
                }
            },
            {
                id: 'modul-5',
                title: 'Stage 4: Closing Strategy (CRA Formula)',
                subtitle: 'Mengunci Komitmen Nyata (Booking DP) secara Amanah & Syar\'i',
                read_time: '4 Menit Baca',
                badge: 'Tahap 4 & CRA',
                objective: 'Mendorong jamaah mengamankan seat melalui minimal DP resmi travel tanpa terkesan memaksa atau menggunakan trik palsu.',
                key_points: [
                    {
                        headline: 'C - Confirm (Konfirmasi Kecocokan)',
                        explanation: 'Pastikan jamaah memang sudah cocok dengan jadwal, paket, dan harga yang ditawarkan.'
                    },
                    {
                        headline: 'R - Reason (Alasan Bertindak Sekarang)',
                        explanation: 'Gunakan urgensi nyata: ketersediaan alokasi kamar hotel musim liburan atau batas issued tiket penerbangan.'
                    },
                    {
                        headline: 'A - Action (Arahkan Pembayaran DP)',
                        explanation: 'Berikan instruksi konkrit pembayaran DP ke rekening resmi PT (bukan rekening pribadi CS).'
                    }
                ],
                principles: [
                    'Closing bukan memaksa jamaah membeli, tetapi membantu jamaah mengambil keputusan ibadah.',
                    'Haram hukumnya membuat batas waktu atau jumlah seat palsu.',
                    'Nomor rekening DP wajib rekening resmi atas nama PT brand travel terkait.'
                ],
                indicator: 'Jamaah melakukan transfer minimal DP atau memberikan komitmen tanggal pasti untuk pembayaran DP.',
                bad_case: {
                    chat: 'CS: "Ya sudah kalau mau daftar nanti kabari saja ya kak."',
                    why_bad: 'Pasif, tidak memberikan alasan untuk bertindak sekarang, dan membiarkan calon jamaah mendingin.'
                },
                good_case: {
                    chat: 'CS: "Alhamdulillah kalau untuk jadwal dan paketnya Ibu sudah merasa cocok. Mengingat alokasi seat pesawat untuk tanggal 15 Oktober tersisa 4 kursi lagi, kami sarankan amankan seat Ibu sekeluarga dengan DP Rp 5jt/pax hari ini ya Bu. Transfer langsung ke rekening resmi kami di BSI No. 7188291021 a/n PT Azhan Wisata Mandiri. Setelah transfer, bukti kirim ke sini agar seat langsung kami kunci."',
                    why_good: 'Jelas, amanah, menyertakan rekening resmi PT, dan memberikan langkah tindakan yang tegas.'
                }
            },
            {
                id: 'modul-6',
                title: 'Stage 5: Handling Objection (TGJP Framework)',
                subtitle: 'Seni Menyelesaikan Keraguan Jamaah dengan Empati & Solusi',
                read_time: '6 Menit Baca',
                badge: 'Tahap 5 & TGJP',
                objective: 'Menguasai 4 langkah TGJP: Terima keberatan dengan empati, Gali akar masalah, Jawab dengan solusi tepat, dan Pastikan kesiapan closing.',
                key_points: [
                    {
                        headline: 'T - Terima (Validasi Tanpa Membantah)',
                        explanation: 'Jangan defensif! Katakan: "Saya sangat paham Kak, soal biaya memang perlu perhitungan matang."'
                    },
                    {
                        headline: 'G - Gali (Temukan Akar Sebenarnya)',
                        explanation: 'Tanyakan: "Kalau boleh tahu, yang terasa berat di total paketnya atau di pembayaran DP awalnya Kak?"'
                    },
                    {
                        headline: 'J - Jawab (Berikan Solusi Sesuai Akar)',
                        explanation: 'Jika masalahnya budget -> sesuaikan paket ke bintang 3. Jika masalahnya belum melihat value -> jelaskan benefit hotel dekat.'
                    },
                    {
                        headline: 'P - Pastikan (Kunci Kembali)',
                        explanation: 'Tanyakan: "Kalau untuk skema pembayarannya bisa dicicil bertahap, apakah jadwalnya sudah cocok Kak?"'
                    }
                ],
                principles: [
                    'Jangan langsung menjawab sebelum menggali akar masalah sebenarnya.',
                    'Jangan pernah menjelek-jelekkan travel kompetitor.',
                    'Jika jamaah butuh waktu rembukan keluarga, hormati dan tetapkan jadwal follow-up.'
                ],
                indicator: 'Keberatan jamaah terjawab tuntas dan percakapan kembali diarahkan menuju Closing atau Follow-up terjadwal.',
                bad_case: {
                    chat: 'Jamaah: "Kok mahal banget ya travel sebelah cuma 23 juta."\nCS: "Ya kalau 23 juta mah travel bodong kak, hotelnya di gurun pasir mau? Travel kami resmi bintang 5!"',
                    why_bad: 'Defensif, arogan, menjelekkan pihak lain, dan membuat jamaah antipati seketika.'
                },
                good_case: {
                    chat: 'Jamaah: "Kok mahal banget ya travel sebelah cuma 23 juta."\nCS: "Iya Kak, wajar banget kalau harga jadi pertimbangan utama. Kalau boleh tahu, paket 23 juta tadi hotelnya bintang berapa dan jaraknya berapa meter ke masjid ya Kak? Supaya adil, mari kita bandingkan fasilitas setaranya. Di paket kami, harga sudah all-in termasuk tiket langsung tanpa transit dan hotel dekat pelataran agar ibadah Kakak tidak kelelahan. Jika budget Kakak ingin lebih hemat, kami juga punya pilihan paket 27 jutaan yang fasilitasnya tetap terjamin resmi Kak."',
                    why_good: 'Menerima dengan santun, mengedukasi cara membandingkan secara adil, dan menawarkan opsi solusi yang relevan.'
                }
            },
            {
                id: 'modul-7',
                title: 'Stage 6: Follow-up Berbobot (CCN Formula)',
                subtitle: 'Menindaklanjuti Chat yang Terhenti Tanpa Menjadi Spam',
                read_time: '4 Menit Baca',
                badge: 'Tahap 6 & CCN',
                objective: 'Menguasai formula Context - Check - Next Step agar pesan follow-up selalu relevan dan dihargai oleh calon jamaah.',
                key_points: [
                    {
                        headline: 'C - Context (Kaitkan dengan Obrolan Terakhir)',
                        explanation: 'Ingatkan topik terakhir yang dibahas (misal: diskusi keluarga, pengecekan cuti kerja, atau pemilihan paket).'
                    },
                    {
                        headline: 'C - Check (Tanyakan Perkembangannya)',
                        explanation: 'Tanyakan dengan santun apakah sudah ada keputusan atau kendala baru yang dihadapi.'
                    },
                    {
                        headline: 'N - Next Step (Sodorkan 1 Langkah Tindak Lanjut)',
                        explanation: 'Tawarkan bantuan konsultasi lanjutan, penyesuaian jadwal, atau penetapan status jika belum siap.'
                    }
                ],
                principles: [
                    'Jangan mengirim pesan follow-up generik seperti "Pagi kak, jadi daftar?".',
                    'Maksimal follow-up 3-4 kali dengan jeda waktu yang wajar (2-3 hari).',
                    'Jika jamaah belum siap sekarang, ubah status ke Nurture dan jangan terus menerus diteror.'
                ],
                indicator: 'CS mendapatkan kepastian status prospek: lanjut closing, nurture (jadwalkan follow-up masa depan), atau closed lost (batal).',
                bad_case: {
                    chat: 'CS (Pagi): "Pagi kak"\nCS (Siang): "Kakak jadi umroh nggak?"\nCS (Malam): "Kok cuma di-read aja kak?"',
                    why_bad: 'Spamming, tidak sopan, menimbulkan rasa bersalah yang tidak nyaman bagi jamaah.'
                },
                good_case: {
                    chat: 'CS: "Assalamu\'alaikum Ibu Rina. Semoga sehat selalu sekeluarga. Menyambung diskusi kita kemarin mengenai rencana umroh akhir tahun bersama suami, apakah sudah sempat dibicarakan dengan beliau Bu? Jika masih ada bagian jadwal atau fasilitas yang ingin disesuaikan, saya siap bantu carikan alternatifnya ya Bu."',
                    why_good: 'Sopan, mengangkat konteks diskusi suami kemarin, dan memposisikan diri siap membantu, bukan mengejar komisi.'
                }
            },
            {
                id: 'modul-8',
                title: 'Kamus 9 Status Prospek & Pipeline',
                subtitle: 'Standarisasi Klasifikasi Prospek agar Nol Data Tercecer',
                read_time: '4 Menit Baca',
                badge: 'Manajemen Prospek',
                objective: 'Mampu mengkategorikan setiap calon jamaah ke dalam 9 status resmi secara tepat waktu dan akurat di Mini Lead Tracker.',
                key_points: [
                    {
                        headline: '1. New (Prospek Baru)',
                        explanation: 'Jamaah baru menyapa atau mengisi formulir, belum masuk tahap kualifikasi kebutuhan.'
                    },
                    {
                        headline: '2. Identifying (Sedang Diidentifikasi)',
                        explanation: 'CS sedang aktif menggali data jadwal, budget, peserta, dan unsur NPGD.'
                    },
                    {
                        headline: '3. Offered (Sudah Diberikan Penawaran)',
                        explanation: 'Paket rekomendasi beserta detail harga sudah disampaikan kepada jamaah.'
                    },
                    {
                        headline: '4. Objection (Ada Keberatan)',
                        explanation: 'Jamaah menyampaikan keraguan soal harga, izin keluarga, cuti, atau keraguan legalitas.'
                    },
                    {
                        headline: '5. Closing (Proses Closing)',
                        explanation: 'Jamaah sudah cocok dan dalam proses konfirmasi atau menunggu pembayaran DP.'
                    },
                    {
                        headline: '6. Follow-up (Perlu Follow-up)',
                        explanation: 'Jamaah sempat berhenti membalas dan memiliki jadwal tindak lanjut.'
                    },
                    {
                        headline: '7. Nurture (Belum Sekarang)',
                        explanation: 'Jamaah masih berkeinginan tapi baru siap tahun depan atau menunggu tabungan cukup.'
                    },
                    {
                        headline: '8. Closed Won (Closing Berhasil)',
                        explanation: 'Pembayaran DP resmi sudah diterima dan seat keberangkatan terkunci.'
                    },
                    {
                        headline: '9. Closed Lost (Batal)',
                        explanation: 'Jamaah batal berangkat (sudah daftar travel lain, sakit berat, dll) dengan alasan jelas.'
                    }
                ],
                principles: [
                    'Update status di Mini Lead Tracker setiap kali selesai mengobrol dengan jamaah.',
                    'Status Nurture wajib memiliki tanggal follow-up berikutnya (misal: 3 bulan lagi).',
                    'Status Closed Lost wajib mencantumkan alasan batal di kolom catatan NPGD.'
                ],
                indicator: 'Semua prospek yang dipegang CS terdaftar dengan status yang akurat di pipeline tanpa ada yang terlewat.',
                bad_case: {
                    chat: 'CS membiarkan 50 kontak chat di WhatsApp tanpa label dan lupa mana yang sudah transfer dan mana yang minta dihubungi minggu depan.',
                    why_bad: 'Banyak calon jamaah potensial hilang begitu saja karena CS lupa menindaklanjuti.'
                },
                good_case: {
                    chat: 'CS mencatat setiap prospek di Lead Tracker: Bu Siti (Closing - Tunggu DP), Pak Hendra (Nurture - Follow-up 10 Oktober), Bu Rina (Objection - Harga).',
                    why_good: 'Operasional tertib, terukur, dan konversi closing meningkat signifikan.'
                }
            },
            {
                id: 'modul-9',
                title: 'Kode Etik CS Umroh & Anti-Overpromise',
                subtitle: 'Menjaga Keberkahan Ibadah & Integritas Perusahaan Travel',
                read_time: '3 Menit Baca',
                badge: 'Etika & Syariah',
                objective: 'Menerapkan adab syar\'i dalam pelayanan jamaah: jujur, transparan, dan tidak memberikan janji palsu yang merugikan perusahaan maupun jamaah.',
                key_points: [
                    {
                        headline: 'Kejujuran Fasilitas (Anti-Overpromise)',
                        explanation: 'Jangan pernah menjanjikan hotel "depan masjid" jika nyatanya berjarak 500 meter. Jelaskan jarak nyata secara jujur.'
                    },
                    {
                        headline: 'Ketentuan Pembatalan & Pengembalian Dana',
                        explanation: 'Jelaskan hak dan kewajiban jamaah secara adil sesuai ketentuan PPIU Kemenag yang berlaku.'
                    },
                    {
                        headline: 'Menjaga Rahasia & Data Calon Jamaah',
                        explanation: 'Nomor telepon dan data pribadi jamaah adalah amanah yang wajib dijaga kerahasiaannya.'
                    }
                ],
                principles: [
                    'Bisnis umroh adalah bisnis mengantar tamu Allah, keberkahan jauh lebih tinggi nilainya daripada komisi sesaat.',
                    'Satu janji palsu akan merusak reputasi travel yang dibangun bertahun-tahun.',
                    'Selalu doakan kemudahan bagi setiap calon jamaah yang Anda layani.'
                ],
                indicator: 'CS melayani jamaah dengan integritas tinggi, tanpa komplain overpromise di kemudian hari.',
                bad_case: {
                    chat: 'CS: "Iya bu tenang aja, hotelnya nempel pelataran kok, jalan kaki cuma 1 menit!" (Padahal aslinya hotel bintang 3 di kawasan Syisyah yang harus naik bus shuttle)',
                    why_bad: 'Penipuan konsumen yang merusak citra travel dan menimbulkan kekecewaan besar saat jamaah tiba di Tanah Suci.'
                },
                good_case: {
                    chat: 'CS: "Untuk paket ini hotel di Makkah berjarak sekitar 350 meter dari pelataran Bu, waktu jalan kaki sekitar 5-7 menit santai. Kondisi jalannya datar. InsyaAllah nyaman dan tetap hemat di budget."',
                    why_good: 'Jujur, transparan, dan memberikan ekspektasi yang tepat kepada jamaah.'
                }
            }
        ],

        get currentModule() {
            return this.modules.find(m => m.id === this.activeModuleId) || this.modules[0];
        },

        get currentModuleIndex() {
            return this.modules.findIndex(m => m.id === this.activeModuleId);
        },

        get completedCount() {
            return this.completedModules.length;
        },

        get progressPercent() {
            return Math.round((this.completedCount / this.modules.length) * 100);
        },

        isCompleted(id) {
            return this.completedModules.includes(id);
        },

        toggleCompleted(id) {
            if (this.isCompleted(id)) {
                this.completedModules = this.completedModules.filter(mId => mId !== id);
            } else {
                this.completedModules.push(id);
                window.showToast('Modul ditandai selesai!');
            }
            localStorage.setItem('csumroh_completed_modules', JSON.stringify(this.completedModules));
        },

        nextModule() {
            if (!this.isCompleted(this.currentModule.id)) {
                this.toggleCompleted(this.currentModule.id);
            }
            if (this.currentModuleIndex < this.modules.length - 1) {
                this.activeModuleId = this.modules[this.currentModuleIndex + 1].id;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                window.showToast('Selamat! Anda telah menyelesaikan seluruh modul LMS');
            }
        },

        prevModule() {
            if (this.currentModuleIndex > 0) {
                this.activeModuleId = this.modules[this.currentModuleIndex - 1].id;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

