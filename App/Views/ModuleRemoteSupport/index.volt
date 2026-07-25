<div id="remote-support-page" class="ui large grey segment module-remote-support">
    <div id="remote-support-summary"
         class="remote-support-summary remote-support-summary-grey"
         role="status"
         aria-live="polite"
         aria-atomic="true">
        <span class="remote-support-summary-led" aria-hidden="true"></span>
        <span id="remote-support-live" class="remote-support-summary-text">
            {{ t._('module_remote_support_StateOff') }}
        </span>
    </div>

    <section class="remote-support-state" data-state-view="off" hidden>
        <div class="ui warning message remote-support-consent">
            <p>{{ t._('module_remote_support_ConsentRoot') }}</p>
            <p>{{ t._('module_remote_support_ConsentWebAdmin') }}</p>
            <p>{{ t._('module_remote_support_ConsentEightHours') }}</p>
            <p>{{ t._('module_remote_support_ConsentRecording') }}</p>
        </div>
        <button id="remote-support-start" class="ui primary button" type="button">
            <i class="unlock alternate icon"></i>
            {{ t._('module_remote_support_Start') }}
        </button>
    </section>

    <section class="remote-support-state remote-support-progress" data-state-view="starting" hidden>
        <div class="ui active centered inline loader"></div>
        <p class="center aligned">{{ t._('module_remote_support_StartingHint') }}</p>
    </section>

    <section class="remote-support-state" data-state-view="active" hidden>
        <div class="remote-support-session">
            <div class="remote-support-code-label">
                {{ t._('module_remote_support_CodeLabel') }}
            </div>
            <div id="remote-support-code"
                 class="remote-support-code"
                 aria-label="{{ t._('module_remote_support_CodeLabel') }}"></div>
            <button id="remote-support-copy" class="ui basic button" type="button">
                <i class="copy outline icon"></i>
                {{ t._('module_remote_support_Copy') }}
            </button>
            <div id="remote-support-countdown" class="remote-support-countdown"></div>
        </div>
        <div id="remote-support-web-access" class="remote-support-web-access" hidden>
            <div class="remote-support-web-title">
                {{ t._('module_remote_support_WebAccessTitle') }}
            </div>
            <p class="remote-support-web-instruction">
                {{ t._('module_remote_support_WebInstruction') }}
            </p>
            <div class="remote-support-web-field">
                <span class="remote-support-web-label">
                    {{ t._('module_remote_support_WebLoginLabel') }}
                </span>
                <code id="remote-support-web-login" class="remote-support-web-value"></code>
                <button id="remote-support-web-copy-login" class="ui basic tiny button" type="button">
                    <i class="copy outline icon"></i>
                    {{ t._('module_remote_support_Copy') }}
                </button>
            </div>
            <div class="remote-support-web-field">
                <span class="remote-support-web-label">
                    {{ t._('module_remote_support_WebPasswordLabel') }}
                </span>
                <code id="remote-support-web-password" class="remote-support-web-value"></code>
                <button id="remote-support-web-copy-password" class="ui basic tiny button" type="button">
                    <i class="copy outline icon"></i>
                    {{ t._('module_remote_support_Copy') }}
                </button>
            </div>
        </div>
        <div class="ui warning message remote-support-consent">
            <p>{{ t._('module_remote_support_ConsentRoot') }}</p>
            <p>{{ t._('module_remote_support_ConsentWebAdmin') }}</p>
            <p>{{ t._('module_remote_support_ConsentRecording') }}</p>
        </div>
        <button id="remote-support-stop" class="ui negative button" type="button">
            <i class="stop circle outline icon"></i>
            {{ t._('module_remote_support_Stop') }}
        </button>
    </section>

    <section class="remote-support-state remote-support-progress" data-state-view="stopping" hidden>
        <div class="ui active centered inline loader"></div>
        <p class="center aligned">{{ t._('module_remote_support_StoppingHint') }}</p>
    </section>

    <section class="remote-support-state" data-state-view="error" hidden>
        <p id="remote-support-error" class="remote-support-error"></p>
        <button id="remote-support-retry" class="ui primary button" type="button">
            <i class="redo icon"></i>
            {{ t._('module_remote_support_Retry') }}
        </button>
    </section>

    <aside class="remote-support-contacts">
        <h4 class="ui header">{{ t._('module_remote_support_Contacts') }}</h4>
        <a id="remote-support-phone"
           class="ui basic button"
           href="tel:+74952293042">
            <i class="phone icon"></i>
            +7 495 229-30-42
        </a>
        <a id="remote-support-telegram"
           class="ui basic button"
           href="https://t.me/Telefon1CBot?start=[mkpbx]"
           target="_blank"
           rel="noopener noreferrer">
            <i class="telegram plane icon"></i>
            Telegram
        </a>
    </aside>
</div>
