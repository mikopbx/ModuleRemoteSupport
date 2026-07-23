<div id="remote-support-page" class="module-remote-support">
    <div class="ui clearing segment remote-support-header">
        <img class="ui tiny left floated image"
             src="{{ logoImagePath }}"
             alt="{{ t._('module_remote_support_Title') }}">
        <h2 class="ui header">{{ t._('module_remote_support_Title') }}</h2>
        <p>{{ t._('module_remote_support_Description') }}</p>
    </div>

    <div id="remote-support-live"
         class="ui message remote-support-live"
         role="status"
         aria-live="polite"
         aria-atomic="true"></div>

    <section class="ui segment remote-support-state" data-state-view="off" hidden>
        <h3 class="ui header">{{ t._('module_remote_support_StateOff') }}</h3>
        <div class="ui warning message remote-support-consent">
            <p>{{ t._('module_remote_support_ConsentRoot') }}</p>
            <p>{{ t._('module_remote_support_ConsentEightHours') }}</p>
            <p>{{ t._('module_remote_support_ConsentRecording') }}</p>
        </div>
        <button id="remote-support-start" class="ui primary button" type="button">
            <i class="unlock alternate icon"></i>
            {{ t._('module_remote_support_Start') }}
        </button>
    </section>

    <section class="ui segment remote-support-state" data-state-view="starting" hidden>
        <div class="ui active centered inline loader"></div>
        <h3 class="ui center aligned header">
            {{ t._('module_remote_support_StateStarting') }}
        </h3>
        <p class="center aligned">{{ t._('module_remote_support_StartingHint') }}</p>
    </section>

    <section class="ui segment remote-support-state" data-state-view="active" hidden>
        <h3 class="ui header">{{ t._('module_remote_support_StateActive') }}</h3>
        <div class="ui positive message">
            <div class="header">{{ t._('module_remote_support_CodeLabel') }}</div>
            <div id="remote-support-code"
                 class="remote-support-code"
                 aria-label="{{ t._('module_remote_support_CodeLabel') }}"></div>
            <button id="remote-support-copy" class="ui basic button" type="button">
                <i class="copy outline icon"></i>
                {{ t._('module_remote_support_Copy') }}
            </button>
            <div id="remote-support-countdown" class="remote-support-countdown"></div>
        </div>
        <div class="ui warning message remote-support-consent">
            <p>{{ t._('module_remote_support_ConsentRoot') }}</p>
            <p>{{ t._('module_remote_support_ConsentRecording') }}</p>
        </div>
        <button id="remote-support-stop" class="ui negative button" type="button">
            <i class="stop circle outline icon"></i>
            {{ t._('module_remote_support_Stop') }}
        </button>
    </section>

    <section class="ui segment remote-support-state" data-state-view="stopping" hidden>
        <div class="ui active centered inline loader"></div>
        <h3 class="ui center aligned header">
            {{ t._('module_remote_support_StateStopping') }}
        </h3>
        <p class="center aligned">{{ t._('module_remote_support_StoppingHint') }}</p>
    </section>

    <section class="ui segment remote-support-state" data-state-view="error" hidden>
        <div class="ui negative message">
            <div class="header">{{ t._('module_remote_support_StateError') }}</div>
            <p id="remote-support-error"></p>
        </div>
        <button id="remote-support-retry" class="ui primary button" type="button">
            <i class="redo icon"></i>
            {{ t._('module_remote_support_Retry') }}
        </button>
    </section>

    <aside class="ui secondary segment remote-support-contacts">
        <h4 class="ui header">{{ t._('module_remote_support_Contacts') }}</h4>
        <a id="remote-support-phone"
           class="ui basic button"
           href="tel:"
           hidden>
            <i class="phone icon"></i>
            <span></span>
        </a>
        <a id="remote-support-telegram"
           class="ui basic button"
           href="https://t.me/"
           target="_blank"
           rel="noopener noreferrer"
           hidden>
            <i class="telegram plane icon"></i>
            <span></span>
        </a>
        <a id="remote-support-website"
           class="ui basic button"
           href="https://www.mikopbx.com/support/"
           target="_blank"
           rel="noopener noreferrer">
            <i class="external alternate icon"></i>
            {{ t._('module_remote_support_Website') }}
        </a>
        <p id="remote-support-contact-fallback" class="remote-support-contact-fallback">
            {{ t._('module_remote_support_ContactFallback') }}
        </p>
    </aside>
</div>
