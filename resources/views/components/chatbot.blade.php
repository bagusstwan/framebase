@props(['footer' => null])

@php
    $whatsappUrl = null;
    if ($footer && !empty($footer->whatsapp_url)) {
        $wa = trim($footer->whatsapp_url);
        if (!str_contains($wa, '6281234567890')) {
            if (!str_starts_with($wa, 'http://') && !str_starts_with($wa, 'https://')) {
                $whatsappUrl = 'https://' . ltrim($wa, '/');
            } else {
                $whatsappUrl = $wa;
            }
        }
    }

    $servicesList = [];
    $servicesData = $services ?? ($__data['services'] ?? ($__env->getShared()['services'] ?? null));
    if (!$servicesData) {
        try {
            if (class_exists(\App\Models\Service::class)) {
                $servicesData = \App\Models\Service::where('is_active', true)->get();
            }
        } catch (\Throwable $e) {
            $servicesData = null;
        }
    }

    if ($servicesData && (is_array($servicesData) || $servicesData instanceof \Traversable || $servicesData instanceof \Illuminate\Support\Collection)) {
        foreach ($servicesData as $s) {
            if (!isset($s->is_active) || $s->is_active) {
                $servicesList[] = [
                    'name' => $s->name,
                ];
            }
        }
    }

    $orderUrl = route('order.page');
@endphp

<div x-data="framebaseChatbot({
        whatsappUrl: @js($whatsappUrl),
        orderUrl: @js($orderUrl),
        services: @js($servicesList)
    })"
    class="fixed bottom-5 right-4 sm:bottom-6 sm:right-6 z-[70] font-sans antialiased text-gray-900"
    @keydown.escape.window="closeChat()">

    <!-- Tombol Pemicu Melayang -->
    <button type="button"
            x-ref="triggerButton"
            @click="toggleChat()"
            :aria-expanded="isOpen.toString()"
            aria-haspopup="dialog"
            aria-label="Tanya studio FrameBase"
            class="flex items-center gap-2.5 bg-[#141414] hover:bg-black text-white px-4 py-2.5 sm:px-5 sm:py-3 rounded-full shadow-xl border border-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 motion-reduce:transition-none">
        
        <svg x-show="!isOpen"
             class="w-4 h-4 text-white flex-shrink-0"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>

        <svg x-show="isOpen"
             class="w-4 h-4 text-white flex-shrink-0"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true"
             x-cloak>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
        </svg>

        <span class="text-xs sm:text-sm font-medium tracking-wide" x-text="isOpen ? 'Tutup' : 'Tanya Kami'"></span>
    </button>

    <!-- Panel Chat Utama -->
    <section x-show="isOpen"
             x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-3 scale-95"
             role="dialog"
             aria-modal="true"
             aria-label="Jendela chat studio FrameBase"
             class="fixed bottom-16 sm:bottom-20 right-4 sm:right-6 w-[calc(100vw-2rem)] max-w-[370px] sm:w-[370px] h-[490px] max-h-[calc(100vh-6rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden z-[70]"
             x-cloak>

        <!-- Header Panel -->
        <header class="bg-[#141414] text-white px-4 py-3 flex items-center justify-between border-b border-gray-800 flex-shrink-0">
            <div>
                <h2 class="font-medium text-sm tracking-tight text-white leading-tight">FrameBase</h2>
                <p class="text-[11px] text-gray-400 font-normal">Balasan otomatis</p>
            </div>

            <button type="button"
                    @click="closeChat()"
                    aria-label="Tutup jendela chat"
                    class="p-1.5 text-gray-400 hover:text-white rounded-lg transition-colors focus:outline-none focus:ring-1 focus:ring-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <!-- Baris Keterangan Layanan -->
        <div class="bg-gray-50 text-gray-500 text-[11px] px-4 py-2 border-b border-gray-200/80 leading-normal flex-shrink-0">
            <span x-text="whatsappUrl ? 'Pertanyaan di luar topik akan diteruskan ke pemilik usaha lewat WhatsApp.' : 'Pertanyaan di luar topik dapat disampaikan melalui formulir pemesanan.'"></span>
        </div>

        <!-- Area Pesan (Scrollable) -->
        <div x-ref="messageList"
             class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#f8f9fa] chatbot-scrollbar">
            
            <template x-for="msg in messages" :key="msg.id">
                <div class="space-y-1">
                    <!-- Balasan Studio -->
                    <template x-if="msg.sender === 'bot'">
                        <div class="flex flex-col items-start max-w-[85%]">
                            <div class="bg-white border border-gray-200/80 text-gray-800 p-3 rounded-2xl text-xs sm:text-sm leading-relaxed whitespace-pre-line shadow-sm">
                                <span x-text="msg.text"></span>

                                <!-- Tombol Aksi Pesan -->
                                <template x-if="msg.action">
                                    <div class="mt-2.5 pt-2 border-t border-gray-100 flex flex-col gap-1 w-full">
                                        <a :href="msg.action.url"
                                           :target="msg.action.external ? '_blank' : '_self'"
                                           @click="msg.action.url.startsWith('#') ? closeChat() : null"
                                           class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-[#141414] hover:bg-black text-white text-xs font-medium rounded-lg transition-colors text-center">
                                            <span x-text="msg.action.label"></span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    </div>
                                </template>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-0.5 ml-1" x-text="msg.time"></span>
                        </div>
                    </template>

                    <!-- Pesan Pengunjung -->
                    <template x-if="msg.sender === 'user'">
                        <div class="flex flex-col items-end max-w-[85%] ml-auto">
                            <div class="bg-[#141414] text-white p-3 rounded-2xl text-xs sm:text-sm leading-relaxed whitespace-pre-line shadow-sm">
                                <span x-text="msg.text"></span>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-0.5 mr-1 text-right" x-text="msg.time"></span>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Indikator Menulis Balasan -->
            <div x-show="isTyping"
                 class="flex items-center gap-1.5 text-xs text-gray-400 py-1"
                 aria-live="polite"
                 x-cloak>
                <span class="text-xs">Sedang menulis balasan</span>
                <span class="inline-flex gap-0.5" aria-hidden="true">
                    <span class="w-1 h-1 rounded-full bg-gray-400 animate-pulse"></span>
                    <span class="w-1 h-1 rounded-full bg-gray-400 animate-pulse" style="animation-delay: 200ms"></span>
                    <span class="w-1 h-1 rounded-full bg-gray-400 animate-pulse" style="animation-delay: 400ms"></span>
                </span>
            </div>
        </div>

        <!-- Tombol Saran Cepat -->
        <div x-show="suggestions.length > 0" class="bg-white px-3 py-2 border-t border-gray-100 flex-shrink-0">
            <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-0.5">
                <template x-for="item in suggestions" :key="item">
                    <button type="button"
                            @click="useSuggestion(item)"
                            :disabled="isTyping"
                            class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg text-xs font-normal whitespace-nowrap transition-colors flex-shrink-0 focus:outline-none focus:ring-1 focus:ring-gray-900 disabled:opacity-50">
                        <span x-text="item"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Kolom Input Teks -->
        <footer class="p-3 bg-white border-t border-gray-100 flex-shrink-0">
            <form @submit.prevent="submitInput()" class="flex items-center gap-2">
                <input x-ref="chatInput"
                       type="text"
                       x-model="inputText"
                       placeholder="Ketik pertanyaan Anda..."
                       class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs sm:text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:bg-white transition-colors"
                       maxlength="200">
                
                <button type="submit"
                        :disabled="!inputText.trim() || isTyping"
                        aria-label="Kirim pesan"
                        class="px-3 py-2 bg-[#141414] hover:bg-black disabled:opacity-30 disabled:cursor-not-allowed text-white text-xs font-medium rounded-lg transition-colors flex-shrink-0 focus:outline-none focus:ring-1 focus:ring-gray-900">
                    Kirim
                </button>
            </form>
        </footer>
    </section>
</div>

<script>
function framebaseChatbot(config) {
    return {
        isOpen: false,
        hasOpened: false,
        isTyping: false,
        inputText: '',
        whatsappUrl: config.whatsappUrl || null,
        orderUrl: config.orderUrl || '/order',
        services: Array.isArray(config.services) ? config.services : [],
        messages: [],
        suggestions: [
            'Paket dan harga',
            'Cek ketersediaan tanggal',
            'Cara pemesanan',
            'Hubungi pemilik usaha'
        ],

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        },

        openChat() {
            this.isOpen = true;
            if (!this.hasOpened) {
                this.hasOpened = true;
                this.messages.push({
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Halo, ada yang bisa kami bantu? Anda dapat menanyakan paket dan harga, ketersediaan tanggal, atau cara pemesanan.',
                    time: this.getCurrentTime()
                });
            }

            this.$nextTick(() => {
                this.scrollToBottom();
                setTimeout(() => {
                    this.$refs.chatInput?.focus();
                }, 100);
            });
        },

        closeChat() {
            this.isOpen = false;
            this.$nextTick(() => {
                this.$refs.triggerButton?.focus();
            });
        },

        useSuggestion(item) {
            if (this.isTyping) return;
            // Sembunyikan saran yang sudah dipakai agar tidak menumpuk
            this.suggestions = this.suggestions.filter(s => s !== item);
            this.sendMessage(item);
        },

        submitInput() {
            const text = this.inputText.trim();
            if (!text || this.isTyping) return;
            this.inputText = '';
            // Hapus saran terkait jika pengguna mengetik pertanyaan serupa
            this.suggestions = this.suggestions.filter(s => !text.toLowerCase().includes(s.toLowerCase().slice(0, 5)));
            this.sendMessage(text);
        },

        sendMessage(text) {
            this.messages.push({
                id: Date.now(),
                sender: 'user',
                text: text,
                time: this.getCurrentTime()
            });

            this.$nextTick(() => this.scrollToBottom());

            this.isTyping = true;
            this.$nextTick(() => this.scrollToBottom());

            setTimeout(() => {
                const reply = this.resolveReply(text);
                this.isTyping = false;
                this.messages.push(reply);
                this.$nextTick(() => this.scrollToBottom());
            }, 650);
        },

        // =========================================================================
        // SKENARIO JAWABAN STATIS (CLIENT-SIDE)
        // TODO: ganti dengan data paket dan kalender dari backend
        // =========================================================================
        resolveReply(userText) {
            const query = userText.toLowerCase().trim();
            const time = this.getCurrentTime();

            // 1. Skenario: Paket dan Harga
            if (this.matchesAny(query, ['paket', 'harga', 'biaya', 'tarif', 'price'])) {
                let packageNote = '';
                if (this.services && this.services.length > 0) {
                    const sampleNames = this.services.slice(0, 2).map(s => s.name).join(' dan ');
                    packageNote = ' Kami menyediakan pilihan paket seperti ' + sampleNames + '.';
                }

                return {
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Daftar paket dan harga lengkap tersedia di bagian Layanan pada halaman ini.' + packageNote + ' Anda bisa langsung melihat rincian paket dan hasil dokumentasi yang didapatkan di sana.',
                    time: time,
                    action: {
                        label: 'Lihat Layanan & Paket',
                        url: '#order',
                        external: false
                    }
                };
            }

            // 2. Skenario: Ketersediaan Tanggal
            if (this.matchesAny(query, ['tanggal', 'jadwal', 'tersedia', 'ketersediaan', 'slot', 'kapan'])) {
                return {
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Ketersediaan tanggal kami pastikan setelah formulir pemesanan Anda kami terima. Silakan kirimkan tanggal dan lokasi acara Anda melalui formulir.',
                    time: time,
                    action: {
                        label: 'Isi Formulir Pemesanan',
                        url: this.orderUrl,
                        external: false
                    }
                };
            }

            // 3. Skenario: Cara Pemesanan
            if (this.matchesAny(query, ['pesan', 'booking', 'order', 'cara', 'alur', 'tahapan'])) {
                return {
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Pilih paket yang Anda butuhkan di halaman ini, lalu kirimkan detail acara melalui formulir pemesanan. Tim kami akan menghubungi Anda melalui WhatsApp untuk konfirmasi jadwal dan penawaran.',
                    time: time,
                    action: {
                        label: 'Buka Formulir Pemesanan',
                        url: this.orderUrl,
                        external: false
                    }
                };
            }

            // 4. Skenario: Hubungi Pemilik Usaha / Kontak WhatsApp
            if (this.matchesAny(query, ['hubungi', 'pemilik', 'owner', 'admin', 'kontak', 'wa', 'whatsapp', 'telepon'])) {
                if (this.whatsappUrl) {
                    const waLink = this.buildWhatsAppUrl('Halo, saya ingin konsultasi mengenai layanan studio FrameBase.');
                    return {
                        id: Date.now(),
                        sender: 'bot',
                        text: 'Anda bisa langsung mengobrol dengan tim kami melalui WhatsApp untuk konsultasi lebih lanjut.',
                        time: time,
                        action: {
                            label: 'Hubungi via WhatsApp',
                            url: waLink,
                            external: true
                        }
                    };
                }

                return {
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Silakan kirimkan pesan atau kebutuhan acara Anda melalui formulir pemesanan agar tim kami dapat menindaklanjutinya.',
                    time: time,
                    action: {
                        label: 'Buka Formulir Pemesanan',
                        url: this.orderUrl,
                        external: false
                    }
                };
            }

            // 5. Fallback: Pertanyaan di luar topik
            if (this.whatsappUrl) {
                const quote = userText.length > 80 ? userText.slice(0, 80).trim() + '...' : userText.trim();
                const waLink = this.buildWhatsAppUrl('Halo, saya ingin menanyakan: "' + quote + '"');
                return {
                    id: Date.now(),
                    sender: 'bot',
                    text: 'Pertanyaan ini belum bisa kami jawab otomatis. Anda bisa melanjutkan percakapan langsung dengan pemilik studio lewat WhatsApp.',
                    time: time,
                    action: {
                        label: 'Lanjut ke WhatsApp',
                        url: waLink,
                        external: true
                    }
                };
            }

            return {
                id: Date.now(),
                sender: 'bot',
                text: 'Pertanyaan ini belum bisa kami jawab otomatis. Silakan sampaikan melalui formulir pemesanan agar tim kami dapat menindaklanjutinya.',
                time: time,
                action: {
                    label: 'Buka Formulir Pemesanan',
                    url: this.orderUrl,
                    external: false
                }
            };
        },

        matchesAny(text, keywords) {
            return keywords.some(keyword => text.includes(keyword));
        },

        buildWhatsAppUrl(messageText) {
            if (!this.whatsappUrl) return null;
            const sep = this.whatsappUrl.includes('?') ? '&' : '?';
            return this.whatsappUrl + sep + 'text=' + encodeURIComponent(messageText);
        },

        getCurrentTime() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            return `${h}:${m}`;
        },

        scrollToBottom() {
            const container = this.$refs.messageList;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
    };
}
</script>
