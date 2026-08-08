<?php

/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2020-01-03 08:49
 * @Redesigned by       : Pesantren Modern Daarul 'Uluum Lido Theme
 */

use SLiMS\{DB};

$main_template_path = __DIR__ . '/login_template.inc.php';
require_once __DIR__ . '/classic.php';

// set default language
if (isset($_GET['select_lang'])) {
    $select_lang = trim(strip_tags($_GET['select_lang']));
    if (isset($_COOKIE['select_lang'])) {
        @setcookie('select_lang', $select_lang, [
            'expires' => time() - 14400,
            'path' => SWB,
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @setcookie('select_lang', $select_lang, [
        'expires' => time() + 14400,
        'path' => SWB,
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $sysconf['default_lang'] = $select_lang;
} else if (isset($_COOKIE['select_lang'])) {
    $sysconf['default_lang'] = trim(strip_tags($_COOKIE['select_lang']));
}

$data = [];
$activeSchema = DB::getInstance()->query('select * from mst_visitor_room');
if ($activeSchema->rowCount()) {
    $data = $activeSchema->fetchAll(\PDO::FETCH_ASSOC);
}

// Fetch latest 10 visitors for history feed
$latestVisitors = [];
try {
    $recentStmt = DB::getInstance()->query("
        SELECT vc.visitor_id, vc.member_id, vc.member_name, vc.checkin_date, vc.room_code,
               COALESCE(m.member_image, 'photo.png') AS member_image,
               COALESCE(r.name, 'Perpustakaan') AS room_name
        FROM visitor_count AS vc
        LEFT JOIN member AS m ON vc.member_id = m.member_id
        LEFT JOIN mst_visitor_room AS r ON vc.room_code = r.unique_code
        ORDER BY vc.checkin_date DESC
        LIMIT 10
    ");
    if ($recentStmt && $recentStmt->rowCount()) {
        $latestVisitors = $recentStmt->fetchAll(\PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    $latestVisitors = [];
}

// Fetch total visitors count for today
$todayVisitorCount = 0;
try {
    $todayStmt = DB::getInstance()->query("
        SELECT COUNT(*) 
        FROM visitor_count 
        WHERE DATE(checkin_date) = CURRENT_DATE()
    ");
    if ($todayStmt) {
        $todayVisitorCount = (int)$todayStmt->fetchColumn();
    }
} catch (\Throwable $e) {
    $todayVisitorCount = count($latestVisitors);
}

// Fetch top room / visit purpose for today
$topRoomName = 'Perpustakaan';
try {
    $topRoomStmt = DB::getInstance()->query("
        SELECT COALESCE(r.name, vc.room_code) AS room_name, COUNT(*) AS cnt
        FROM visitor_count AS vc
        LEFT JOIN mst_visitor_room AS r ON vc.room_code = r.unique_code
        WHERE DATE(vc.checkin_date) = CURRENT_DATE()
        GROUP BY room_name
        ORDER BY cnt DESC
        LIMIT 1
    ");
    if ($topRoomStmt && $topRoomStmt->rowCount()) {
        $topRoomRow = $topRoomStmt->fetch(\PDO::FETCH_ASSOC);
        if ($topRoomRow && !empty($topRoomRow['room_name'])) {
            $topRoomName = $topRoomRow['room_name'];
        }
    }
} catch (\Throwable $e) {
    $topRoomName = 'Perpustakaan';
}

?>

<div class="vegas-slide"></div>

<div class="du-container" id="visitor_counter">
    <!-- Audio / TTS Initialization Modal -->
    <div v-if="!ttsInitialized" class="du-modal-overlay">
        <div class="du-modal-content">
            <img src="<?php echo assets('images/logo.png'); ?>" alt="Logo Daarul 'Uluum Lido" class="du-modal-logo">
            <h3 class="du-modal-title"><?= $sysconf['library_name']; ?></h3>
            <p class="du-modal-desc"><?= __('Ketuk Lanjutkan untuk mengaktifkan sambutan suara dan memulai anjungan presensi.') ?></p>
            <button type="button" class="du-btn-primary" @click="initTTS">
                <span><?= __('Lanjutkan') ?></span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- Dynamic Header & Live Digital Clock -->
    <header class="du-header">
        <!-- Idle Header: Logo, Institution Title & Digital Clock -->
        <div v-show="textInfo === ''" class="du-header-idle">
            <div class="du-logo-wrapper">
                <img src="<?php echo assets('images/logo.png'); ?>" alt="Pesantren Modern Daarul 'Uluum Lido" class="du-logo-img">
            </div>
            <h1 class="du-institution-name">PESANTREN MODERN DAARUL 'ULUUM LIDO BOGOR</h1>
            <div class="du-kiosk-tagline">
                <i class="fas fa-book-reader"></i>
                <span><?= $sysconf['library_name']; ?> — Anjungan Presensi Presisi</span>
            </div>

            <!-- Live Digital Clock Widget -->
            <div class="du-clock-widget">
                <i class="far fa-clock text-amber-400"></i>
                <span class="du-clock-time">{{ currentTime }}</span>
                <span class="du-clock-date">{{ currentDate }}</span>
            </div>
        </div>

        <!-- Active Greeting Header: Compact Visual Display (Name + Purpose) -->
        <div v-show="textInfo !== ''" class="du-header-active">
            <div class="du-large-avatar-wrapper">
                <img :src="image" alt="Foto Pengunjung" class="du-large-avatar-img" @error="onImageError">
                <div class="du-avatar-status-badge">
                    <i class="fas fa-check"></i>
                </div>
            </div>
            <div class="du-header-active-info">
                <h2 class="du-greeting-msg">
                    {{ memberName }}
                    <span v-if="visitPurposeText" class="du-greeting-purpose">({{ visitPurposeText }})</span>
                </h2>
                <p class="du-greeting-sub">Selamat Datang di Perpustakaan Pesantren Modern Daarul 'Uluum Lido Bogor</p>
            </div>
        </div>
    </header>

    <!-- Main Grid Layout -->
    <div class="du-grid">
        <!-- Left Side Column: Kiosk Stats Bar + Form Card -->
        <div class="du-left-col">
            <!-- Summary Kiosk Stats Bar (Left Column Above Form Card) -->
            <div class="du-stats-bar">
                <div class="du-stat-card">
                    <div class="du-stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="du-stat-info">
                        <div class="du-stat-val">{{ todayVisitorCount }}</div>
                        <div class="du-stat-lbl">Presensi Hari Ini</div>
                    </div>
                </div>
                <div class="du-stat-card">
                    <div class="du-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="du-stat-info">
                        <div class="du-stat-time">07:10 - 21:45</div>
                        <div class="du-stat-lbl">Jam Operasional</div>
                    </div>
                </div>
                <div class="du-stat-card">
                    <div class="du-stat-icon">
                        <i class="fas fa-fire text-amber-500"></i>
                    </div>
                    <div class="du-stat-info">
                        <div class="du-stat-val-sm" title="<?= htmlspecialchars($topRoomName) ?>"><?= htmlspecialchars($topRoomName) ?></div>
                        <div class="du-stat-lbl">Tujuan Terpopuler</div>
                    </div>
                </div>
            </div>

            <!-- Form Kiosk Card with Quote Embedded at Bottom -->
            <div class="du-card">
                <div>
                    <h3 class="du-card-title">
                        <i class="fas fa-user-check text-emerald-600"></i>
                        <span>Form Presensi Masuk</span>
                    </h3>
                    <p class="du-card-subtitle">Ketik Nama atau Scan ID Anggota untuk mencari data anggota aktif.</p>

                    <form @submit.prevent="onSubmit">
                        <!-- Member ID / Name Input with Active Member Autocomplete & Notes Display -->
                        <div class="du-form-group">
                            <label class="du-label" for="memberIdInput"><?= __('Nomor Anggota / ID / Nama') ?></label>
                            <div class="du-input-group">
                                <input v-model="memberId" ref="memberId" autofocus type="text" class="du-input" id="memberIdInput"
                                    @input="onInputMemberId"
                                    @keydown.down.prevent="navigateAutocomplete(1)"
                                    @keydown.up.prevent="navigateAutocomplete(-1)"
                                    @keydown.enter="handleEnterKey"
                                    @keydown.esc="showAutocomplete = false"
                                    @blur="hideAutocompleteDelayed"
                                    placeholder="<?= __('Ketik Nama atau Scan ID Anggota...') ?>" autocomplete="off">
                                <i class="fas fa-search du-input-icon"></i>

                                <!-- Floating Autocomplete Dropdown List with Member Notes -->
                                <div v-if="showAutocomplete && searchResults.length > 0" class="du-autocomplete-dropdown">
                                    <div v-for="(item, idx) in searchResults" :key="item.member_id"
                                        class="du-autocomplete-item"
                                        :class="{ active: idx === selectedSearchIndex }"
                                        @mousedown.prevent="selectMember(item)">
                                        <img :src="getVisitorImage(item.member_image)" alt="Avatar" class="du-ac-avatar" @error="onGridImageError($event)">
                                        <div class="du-ac-info">
                                            <div class="du-ac-name">{{ item.member_name }}</div>
                                            <div class="du-ac-meta">ID: <strong>{{ item.member_id }}</strong> <span v-if="item.inst_name">• {{ item.inst_name }}</span></div>
                                            <div v-if="item.member_notes && item.member_notes.trim() !== ''" class="du-ac-note">
                                                <i class="fas fa-info-circle"></i> {{ item.member_notes }}
                                            </div>
                                        </div>
                                        <i class="fas fa-level-down-alt fa-rotate-90 du-ac-arrow"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Visit Purpose / Room Radio Selector -->
                        <div class="du-form-group">
                            <label class="du-label"><?= __('Tujuan Kunjungan / Ruangan') ?></label>
                            <div class="du-purpose-list">
                                <?php foreach ($data as $item) : ?>
                                    <label class="du-purpose-item" :class="{ active: visitPurpose === '<?= $item['unique_code'] ?>' }">
                                        <div class="du-purpose-left">
                                            <div class="du-purpose-icon-bg">
                                                <i class="fas fa-door-open"></i>
                                            </div>
                                            <span class="du-purpose-text" title="<?= htmlspecialchars($item['name']) ?>"><?= $item['name'] ?></span>
                                        </div>
                                        <input v-model="visitPurpose" type="radio" class="hidden" name="visitPurpose" value="<?= $item['unique_code'] ?>">
                                        <div class="du-check-dot">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="du-btn-primary">
                            <span><?= __('Presensi Masuk') ?></span>
                            <i class="fas fa-sign-in-alt"></i>
                        </button>
                    </form>
                </div>

                <!-- Embedded Quote Widget anchored at bottom of Form Card -->
                <div v-if="quotes && quotes.content" class="du-card-quote">
                    <div class="du-card-quote-box">
                        <i class="fas fa-quote-left du-card-quote-icon"></i>
                        <div class="du-card-quote-content">
                            <p class="du-card-quote-text">{{ quotes.content }}</p>
                            <span class="du-card-quote-author">— {{ quotes.author }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side Column: Full Height Enlarged Recent Visitors Feed -->
        <div class="du-right-col">
            <div class="du-recent-section">
                <div class="du-recent-header">
                    <h4 class="du-recent-title">
                        <i class="fas fa-users text-emerald-600"></i>
                        <span>Pengunjung Terakhir</span>
                    </h4>
                    <span class="du-recent-badge">{{ recentVisitors.length }} Terbaca</span>
                </div>

                <div v-if="recentVisitors.length > 0" class="du-visitor-grid">
                    <div v-for="visitor in recentVisitors" :key="visitor.visitor_id" class="du-visitor-item">
                        <img :src="getVisitorImage(visitor.member_image)" alt="Avatar" class="du-v-avatar" @error="onGridImageError($event)">
                        <div class="du-v-info">
                            <h5 class="du-v-name">{{ visitor.member_name }}</h5>
                            <div class="du-v-meta">
                                <span class="du-v-room">{{ visitor.room_name || 'Perpustakaan' }}</span>
                                <span>• {{ formatTime(visitor.checkin_date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-4 text-slate-500 text-sm">
                    Belum ada data presensi hari ini.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo assets('js/axios.min.js'); ?>"></script>
<script src="<?= JWB . 'he.js' ?>"></script>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<script src="<?php echo assets('js/Speakit.1.1.0.cdn.min.js'); ?>"></script>

<script>
    Pusher.logToConsole = true;

    var pusher = new Pusher('<?= (is_array($env) && isset($env['PUSHER_KEY'])) ? $env['PUSHER_KEY'] : ''; ?>', {
        cluster: 'ap1',
        encrypted: true
    });

    new Vue({
        el: '#visitor_counter',
        data() {
            return {
                memberId: '',
                visitPurpose: '',
                memberName: '',
                visitPurposeText: '',
                textInfo: '',
                image: './images/persons/photo.png',
                quotes: {},
                timeout: null,
                ttsEnabled: false,
                ttsInitialized: false,
                recentVisitors: <?php echo json_encode($latestVisitors); ?>,
                todayVisitorCount: <?php echo $todayVisitorCount; ?>,
                currentTime: '',
                currentDate: '',
                searchResults: [],
                showAutocomplete: false,
                selectedSearchIndex: -1,
                searchDebounce: null
            }
        },
        mounted() {
            this.updateClock();
            setInterval(this.updateClock, 1000);
            this.pusherInit();
            this.getQuotes();
        },
        methods: {
            updateClock: function() {
                let now = new Date();
                this.currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                let days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                let months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                this.currentDate = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
            },
            pusherInit: function() {
                var self = this;
                var channel = pusher.subscribe('my-channel');
                channel.bind('my-event', function(data) {
                    self.textInfo = data.message || '';
                    self.memberName = data.member_name || (data.message ? data.message.split(',')[0] : 'Pengunjung');
                    self.visitPurposeText = data.visit_purpose_text || '';
                    self.image = `./images/persons/${data.member_image || 'photo.png'}`;
                    self.todayVisitorCount++;

                    self.addRecentVisitor({
                        visitor_id: Date.now(),
                        member_id: data.member_id || '',
                        member_name: self.memberName,
                        room_name: self.visitPurposeText || 'Perpustakaan',
                        member_image: data.member_image || 'photo.png',
                        checkin_date: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                    });

                    if (self.ttsEnabled) {
                        self.textToSpeech(data.message || "Selamat datang")
                    }

                    clearTimeout(self.timeout);
                    self.timeout = setTimeout(() => {
                        self.getQuotes();
                        self.resetForm();
                    }, 6000);
                });
            },
            onInputMemberId: function() {
                clearTimeout(this.searchDebounce);
                let kw = this.memberId.trim();
                if (kw.length < 2) {
                    this.searchResults = [];
                    this.showAutocomplete = false;
                    return;
                }
                this.searchDebounce = setTimeout(() => {
                    axios.get('index.php?p=visit&action=search_member&keywords=' + encodeURIComponent(kw))
                        .then(res => {
                            this.searchResults = res.data || [];
                            this.showAutocomplete = this.searchResults.length > 0;
                            this.selectedSearchIndex = -1;
                        })
                        .catch(() => {
                            this.searchResults = [];
                            this.showAutocomplete = false;
                        });
                }, 250);
            },
            selectMember: function(memberObj) {
                this.memberId = memberObj.member_id;
                this.searchResults = [];
                this.showAutocomplete = false;
            },
            navigateAutocomplete: function(step) {
                if (!this.showAutocomplete || this.searchResults.length === 0) return;
                let nextIdx = this.selectedSearchIndex + step;
                if (nextIdx < 0) nextIdx = 0;
                if (nextIdx >= this.searchResults.length) nextIdx = this.searchResults.length - 1;
                this.selectedSearchIndex = nextIdx;
            },
            handleEnterKey: function(e) {
                if (this.showAutocomplete && this.selectedSearchIndex >= 0 && this.selectedSearchIndex < this.searchResults.length) {
                    e.preventDefault();
                    this.selectMember(this.searchResults[this.selectedSearchIndex]);
                } else {
                    this.showAutocomplete = false;
                    this.onSubmit();
                }
            },
            hideAutocompleteDelayed: function() {
                setTimeout(() => {
                    this.showAutocomplete = false;
                }, 200);
            },
            onImageError: function() {
                this.image = './images/persons/photo.png';
            },
            onGridImageError: function(e) {
                e.target.src = './images/persons/photo.png';
            },
            getVisitorImage: function(img) {
                if (!img) return './images/persons/photo.png';
                if (img.startsWith('http') || img.startsWith('./')) return img;
                return `./images/persons/${img}`;
            },
            formatTime: function(dateStr) {
                if (!dateStr) return '';
                if (dateStr.includes(':')) {
                    let parts = dateStr.split(' ');
                    let timePart = parts[parts.length - 1];
                    let sub = timePart.split(':');
                    if (sub.length >= 2) return `${sub[0]}:${sub[1]}`;
                }
                return dateStr;
            },
            addRecentVisitor: function(visitorObj) {
                this.recentVisitors.unshift(visitorObj);
                if (this.recentVisitors.length > 10) {
                    this.recentVisitors.pop();
                }
            },
            getQuotes: function() {
                axios.get('https://slims.web.id/kutipan/')
                    .then(res => {
                        res.data.content = he.decode(res.data.content)
                        this.quotes = res.data
                    })
                    .catch(() => {
                        this.quotes = {
                            content: "Sing penting madhiang.",
                            author: "Pai-Jo"
                        }
                    })
                    .finally(() => {
                        this.textInfo = ''
                        this.memberName = ''
                        this.visitPurposeText = ''
                    })
            },
            initTTS: function() {
                if (this.ttsInitialized) return;
                this.ttsInitialized = true;
                this.ttsEnabled = true;

                Speakit.utteranceVolume = 0;
                Speakit.readText(' ', '<?php echo str_replace('_', '-', $sysconf['default_lang']); ?>').catch(() => {});

                this.$nextTick(() => {
                    if (this.$refs.memberId) {
                        this.$refs.memberId.focus();
                    }
                });
            },
            onSubmit: function() {
                this.initTTS();

                if (!this.memberId.trim()) {
                    alert('<?= __("Nomor ID Anggota Wajib Diisi") ?>');
                    return;
                }
                
                if (!this.visitPurpose.trim()) {
                    alert('<?= __("Silakan Pilih Tujuan Kunjungan") ?>');
                    return;
                }
                let url = 'index.php?p=visit&room=' + encodeURIComponent(this.visitPurpose);
                let data = new FormData()
                data.append('memberID', this.memberId)
                data.append('institution', this.institution)
                data.append('visitPurpose', this.visitPurpose)
                data.append('counter', 1)
                data.append('socket_id', pusher.connection.socket_id)

                axios({
                        url: url,
                        method: 'post',
                        data: data,
                        headers: {
                            'Content-Type': 'multipart/form-data',
                            'X-Response-With': 'application/json'
                        }
                    })
                    .then(res => {
                        this.textInfo = res.data.message || ''
                        this.todayVisitorCount++;
                        
                        // Extract real member name from response or greeting string
                        let realName = res.data.member_name || res.data.memberName;
                        if (!realName && this.textInfo) {
                            realName = this.textInfo.split(',')[0].trim();
                        }
                        if (!realName) {
                            realName = this.memberId;
                        }

                        this.memberName = realName;
                        this.visitPurposeText = res.data.visit_purpose_text || '';
                        this.image = `./images/persons/${res.data.image || 'photo.png'}`;
                        
                        this.addRecentVisitor({
                            visitor_id: Date.now(),
                            member_id: res.data.member_id || this.memberId,
                            member_name: realName,
                            room_name: this.visitPurposeText || 'Perpustakaan',
                            member_image: res.data.image || 'photo.png',
                            checkin_date: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                        });

                        <?php if ($sysconf['template']['visitor_log_voice']) : ?>
                            if (this.textInfo) this.textToSpeech(this.textInfo.replace(/(<([^>]+)>)/ig, ''))
                        <?php endif; ?>
                    })
                    .catch(err => {
                        console.log(err);
                    })
                    .finally(() => {
                        this.resetForm()
                        clearTimeout(this.timeout)
                        this.timeout = setTimeout(() => {
                            this.getQuotes()
                        }, 6000)
                    })
            },
            resetForm: function() {
                this.memberId = ''
                this.visitPurpose = ''
                this.searchResults = []
                this.showAutocomplete = false
                if (this.$refs.memberId) {
                    this.$refs.memberId.focus()
                }
            },
            textToSpeech: function(message) {
                Speakit.utteranceVolume = 1;
                Speakit.readText(message, '<?php echo str_replace('_', '-', $sysconf['default_lang']); ?>')
                    .catch(err => console.log(err));
            }
        }
    })
</script>