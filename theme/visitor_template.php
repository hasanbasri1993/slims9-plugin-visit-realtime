<?php

/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2020-01-03 08:49
 * @File name           : visitor_template.php
 */

$main_template_path = __DIR__ . '/login_template.inc.php';
// set default language
if (isset($_GET['select_lang'])) {
    $select_lang = trim(strip_tags($_GET['select_lang']));
    // delete previous language cookie
    if (isset($_COOKIE['select_lang'])) {
        #@setcookie('select_lang', $select_lang, time()-14400, SWB);
        #@setcookie('select_lang', $select_lang, time()-14400, SWB, "", FALSE, TRUE);

        @setcookie('select_lang', $select_lang, [
            'expires' => time() - 14400,
            'path' => SWB,
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    // create language cookie
    #@setcookie('select_lang', $select_lang, time()+14400, SWB);
    #@setcookie('select_lang', $select_lang, time()+14400, SWB, "", FALSE, TRUE);

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

?>
<div class="vegas-slide" style="position: fixed; z-index: -1"></div>
<div class="flex h-screen w-full" id="visitor_counter" style="background: rgba(0,0,0,0.3)">
    <div v-if="textInfo !== ''" class="rounded p-2 mt-4 bg-blue-lighter text-blue-darker md:hidden">{{textInfo}}</div>


    <div class="flex-1 hidden md:block">
        <div class="h-screen">
            <div v-show="textInfo !== ''" class="flex items-center h-screen p-8">
                <div class="w-32">
                    <div class="w-32 h-32 bg-white rounded-full border-white border-4 shadow">
                        <img :src="image" alt="image" class="rounded-full" @error="onImageError">
                    </div>
                </div>
                <div class="px-8">
                    <h3 class="font-light text-white mb-2" v-html="textInfo"></h3>
                </div>
            </div>
            <div class="flex h-screen items-end p-8">
                <blockquote class="blockquote" v-show="textInfo === ''">
                    <p class="text-white">{{quotes.content}}</p>
                    <footer class="blockquote-footer text-grey-light">{{quotes.author}}</footer>
                </blockquote>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $sysconf['template']['dir'] . '/' . $sysconf['template']['theme'] . '/assets/js/axios.min.js'; ?>"></script>
<script src="<?= JWB . 'he.js' ?>"></script>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

<script>
    // Enable pusher logging - don't include this in production
    Pusher.logToConsole = false;

    var pusher = new Pusher('<?= $env['PUSHER_KEY'] ; ?>', {
        cluster: 'ap1',
        encrypted: true
    });

    new Vue({
        el: '#visitor_counter',
        data() {
            return {
                memberId: '',
                institution: '',
                textInfo: '',
                image: './images/persons/photo.png',
                quotes: {},
                timeout: null,
                ttsEnabled: false,
                ttsInitialized: false
            }
        },
        mounted() {
            // Store Vue instance globally for Pusher access
            this.pusherInit()
            this.getQuotes()
            // Initialize text-to-speech on first user interaction
            this.initTextToSpeech()
        },
        methods: {
            pusherInit: function() {
                var self = this; // Store Vue instance reference
                var channel = pusher.subscribe('my-channel');
                channel.bind('my-event', function(data) {
                    self.memberId = data.member_id || '';
                    self.textInfo = data.message || '';
                    self.image = `./images/persons/${data.member_image || 'photo.png'}`;
                    self.institution = data.institution || '';
                    
                    // Only play TTS if it's been initialized by user interaction
                    if (self.ttsEnabled) {
                        self.textToSpeech(data.message || "selamat malam")
                    }

                    // Clear timeout and reset after 5 seconds
                    clearTimeout(self.timeout);
                    self.timeout = setTimeout(() => {
                        self.getQuotes();
                    }, 5000);
                });
            },
            onImageError: function() {
                this.image = './images/persons/photo.png'
            },
            getQuotes: function() {
                // Alternative Free Quotes API: https://api.quotable.io/random
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
                    })
            },
            resetForm: function() {
                this.memberId = ''
                this.institution = ''
                this.$refs.memberId.focus()
            },
            initTextToSpeech: function() {
                var self = this;
                
                // Add event listeners for user interaction to enable TTS
                var enableTTS = function() {
                    if (!self.ttsInitialized) {
                        self.ttsInitialized = true;
                        self.ttsEnabled = true;
                        // Remove event listeners after first interaction
                        document.removeEventListener('click', enableTTS);
                        document.removeEventListener('keydown', enableTTS);
                        document.removeEventListener('touchstart', enableTTS);
                    }
                };
                
                // Listen for various user interactions
                document.addEventListener('click', enableTTS);
                document.addEventListener('keydown', enableTTS);
                document.addEventListener('touchstart', enableTTS);
            },
            enableTextToSpeech: function() {
                this.ttsEnabled = true;
                this.ttsInitialized = true;
            },
            textToSpeech: function(message) {
                if (!this.ttsEnabled) {
                    console.log('TTS not enabled yet - waiting for user interaction');
                    return;
                }
                
                var utterance = new SpeechSynthesisUtterance(message);
                var voices = speechSynthesis.getVoices();
                utterance['volume'] = 1;
                utterance['rate'] = 1;
                utterance['pitch'] = 1;
                utterance['lang'] = 'id-ID';
                utterance['voice'] = null;
                
                // Try to find Indonesian voice
                for (var i = 0; i < voices.length; i++) {
                    if (voices[i].lang.includes('id')) {
                        utterance['voice'] = voices[i];
                        break;
                    }
                }
                
                console.log('Playing TTS:', message);
                speechSynthesis.cancel();
                speechSynthesis.speak(utterance);
            }
        }
    })
</script>