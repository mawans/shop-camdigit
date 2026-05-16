{*
 * CamDigit .CM Domain Order Page
 * Template file: templates/six/cm-domain-order.tpl
 * Access via:    https://shop-camdigit.cm/index.php?rp=/cm-domain-order
 *
 * Three-step flow:
 *   Step 1 — Domain search (.cm availability check)
 *   Step 2 — Account details
 *   Step 3 — Confirmation / success
 *}
<div class="cm-order-page">

    <!-- ── Step Indicator ─────────────────────────────────────────────────── -->
    <div class="cm-steps" id="cmSteps">
        <div class="cm-step active" data-step="1">
            <div class="cm-step-icon"><i class="fas fa-search"></i></div>
            <span>{if $language == 'french'}Recherche{else}Search{/if}</span>
        </div>
        <div class="cm-step-line"></div>
        <div class="cm-step" data-step="2">
            <div class="cm-step-icon"><i class="fas fa-user"></i></div>
            <span>{if $language == 'french'}Vos coordonnées{else}Your Details{/if}</span>
        </div>
        <div class="cm-step-line"></div>
        <div class="cm-step" data-step="3">
            <div class="cm-step-icon"><i class="fas fa-check"></i></div>
            <span>{if $language == 'french'}Confirmation{else}Confirmation{/if}</span>
        </div>
    </div>

    <!-- ── Global alert banner ────────────────────────────────────────────── -->
    <div class="cm-alert hidden" id="cmAlert" role="alert"></div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 1 – Domain Search                                              -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="cm-panel" id="cmStep1">
        <div class="cm-panel-inner">
            <h2 class="cm-panel-title">
                {if $language == 'french'}
                    Enregistrez votre domaine <span class="cm-tld">.cm</span>
                {else}
                    Register your <span class="cm-tld">.cm</span> domain
                {/if}
            </h2>
            <p class="cm-panel-subtitle">
                {if $language == 'french'}
                    Trouvez votre identité en ligne camerounaise dès aujourd'hui.
                {else}
                    Find your Cameroonian online identity today.
                {/if}
            </p>

            <form id="cmSearchForm" autocomplete="off" novalidate>
                <div class="cm-search-row">
                    <div class="cm-search-input-wrap">
                        <input
                            type="text"
                            id="cmSld"
                            name="sld"
                            class="cm-search-input"
                            placeholder="{if $language == 'french'}votre-nom{else}your-name{/if}"
                            autocapitalize="none"
                            spellcheck="false"
                            maxlength="63"
                            required
                        />
                        <span class="cm-tld-badge">.cm</span>
                    </div>
                    <button type="submit" class="theme-btn cm-search-btn" id="cmSearchBtn">
                        <span class="cm-btn-text">
                            {if $language == 'french'}Rechercher{else}Search{/if}
                        </span>
                        <i class="fas fa-circle-notch fa-spin cm-spinner hidden"></i>
                    </button>
                </div>
                <p class="cm-search-hint">
                    {if $language == 'french'}
                        Entrez uniquement la partie avant ".cm" — les lettres, chiffres et tirets sont autorisés.
                    {else}
                        Enter only the part before ".cm" — letters, numbers and hyphens allowed.
                    {/if}
                </p>
            </form>

            <!-- Availability result (shown after search) -->
            <div id="cmDomainResult" class="cm-domain-result hidden">
                <div class="cm-domain-result-inner">
                    <div class="cm-domain-badge" id="cmDomainBadge"></div>
                    <div class="cm-domain-info">
                        <p class="cm-domain-name" id="cmDomainName"></p>
                        <p class="cm-domain-status" id="cmDomainStatus"></p>
                    </div>
                    <button type="button" class="theme-btn cm-proceed-btn hidden" id="cmProceedBtn">
                        {if $language == 'french'}Continuer <i class="fas fa-arrow-right"></i>{else}Continue <i class="fas fa-arrow-right"></i>{/if}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 2 – Account / Contact Details                                  -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="cm-panel hidden" id="cmStep2">
        <div class="cm-panel-inner">
            <h2 class="cm-panel-title">
                {if $language == 'french'}Vos coordonnées{else}Your Details{/if}
            </h2>
            <p class="cm-panel-subtitle">
                {if $language == 'french'}
                    Un compte WHMCS sera créé avec ces informations.
                {else}
                    A WHMCS account will be created with this information.
                {/if}
            </p>

            <form id="cmDetailsForm" novalidate autocomplete="on">

                <div class="cm-order-summary-bar">
                    <i class="fas fa-globe"></i>
                    <strong>{if $language == 'french'}Domaine :{else}Domain:{/if}</strong>
                    <span id="cmOrderDomainLabel" class="cm-highlight-domain"></span>
                    <button type="button" class="cm-change-domain" id="cmChangeDomain">
                        {if $language == 'french'}Changer{else}Change{/if}
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmFirstname">
                                {if $language == 'french'}Prénom{else}First Name{/if} <span class="cm-required">*</span>
                            </label>
                            <input type="text" id="cmFirstname" name="firstname" class="form-control" required autocomplete="given-name" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmLastname">
                                {if $language == 'french'}Nom{else}Last Name{/if} <span class="cm-required">*</span>
                            </label>
                            <input type="text" id="cmLastname" name="lastname" class="form-control" required autocomplete="family-name" />
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmEmail">
                                {if $language == 'french'}Adresse email{else}Email Address{/if} <span class="cm-required">*</span>
                            </label>
                            <input type="email" id="cmEmail" name="email" class="form-control" required autocomplete="email" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmPhone">
                                {if $language == 'french'}Téléphone{else}Phone Number{/if}
                            </label>
                            <input type="tel" id="cmPhone" name="phone" class="form-control" autocomplete="tel" placeholder="+237 6XX XX XX XX" />
                        </div>
                    </div>
                </div>

                <div class="form-group cm-form-group">
                    <label for="cmAddress">
                        {if $language == 'french'}Adresse{else}Address{/if} <span class="cm-required">*</span>
                    </label>
                    <input type="text" id="cmAddress" name="address1" class="form-control" required autocomplete="street-address" />
                </div>

                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group cm-form-group">
                            <label for="cmCity">
                                {if $language == 'french'}Ville{else}City{/if} <span class="cm-required">*</span>
                            </label>
                            <input type="text" id="cmCity" name="city" class="form-control" required autocomplete="address-level2" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group cm-form-group">
                            <label for="cmPostcode">
                                {if $language == 'french'}Code postal{else}Postcode{/if}
                            </label>
                            <input type="text" id="cmPostcode" name="postcode" class="form-control" autocomplete="postal-code" />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group cm-form-group">
                            <label for="cmCountry">
                                {if $language == 'french'}Pays{else}Country{/if} <span class="cm-required">*</span>
                            </label>
                            <select id="cmCountry" name="country" class="form-control" required autocomplete="country">
                                <option value="">{if $language == 'french'}Sélectionner...{else}Select...{/if}</option>
                                {foreach $countries as $code => $name}
                                    <option value="{$code}"{if $code == 'CM'} selected{/if}>{$name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>

                <hr class="cm-divider" />

                <h4 class="cm-section-heading">
                    <i class="fas fa-lock"></i>
                    {if $language == 'french'}Créez votre mot de passe{else}Create your password{/if}
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmPassword">
                                {if $language == 'french'}Mot de passe{else}Password{/if} <span class="cm-required">*</span>
                            </label>
                            <div class="cm-password-wrap">
                                <input type="password" id="cmPassword" name="password" class="form-control" required autocomplete="new-password" minlength="8" />
                                <button type="button" class="cm-eye-btn" id="cmTogglePw" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group cm-form-group">
                            <label for="cmPassword2">
                                {if $language == 'french'}Confirmer le mot de passe{else}Confirm Password{/if} <span class="cm-required">*</span>
                            </label>
                            <input type="password" id="cmPassword2" name="password2" class="form-control" required autocomplete="new-password" minlength="8" />
                        </div>
                    </div>
                </div>
                <div class="cm-pw-strength" id="cmPwStrength">
                    <div class="cm-pw-bar" id="cmPwBar"></div>
                    <span class="cm-pw-label" id="cmPwLabel"></span>
                </div>

                <div class="form-check cm-terms-check">
                    <input class="form-check-input" type="checkbox" id="cmTerms" required />
                    <label class="form-check-label" for="cmTerms">
                        {if $language == 'french'}
                            J'accepte les <a href="{$WEB_ROOT}/contact.php" target="_blank">conditions générales</a>.
                        {else}
                            I agree to the <a href="{$WEB_ROOT}/contact.php" target="_blank">Terms &amp; Conditions</a>.
                        {/if}
                    </label>
                </div>

                <div class="cm-form-actions">
                    <button type="button" class="btn btn-default cm-back-btn" id="cmBackBtn">
                        <i class="fas fa-arrow-left"></i>
                        {if $language == 'french'}Retour{else}Back{/if}
                    </button>
                    <button type="submit" class="theme-btn cm-submit-btn" id="cmSubmitBtn">
                        <span class="cm-btn-text">
                            {if $language == 'french'}Passer la commande{else}Place Order{/if}
                        </span>
                        <i class="fas fa-circle-notch fa-spin cm-spinner hidden"></i>
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- STEP 3 – Success                                                    -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="cm-panel hidden" id="cmStep3">
        <div class="cm-panel-inner cm-success-panel">
            <div class="cm-success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="cm-panel-title">
                {if $language == 'french'}Commande confirmée !{else}Order Confirmed!{/if}
            </h2>
            <p class="cm-panel-subtitle" id="cmSuccessMsg"></p>

            <div class="cm-success-details" id="cmSuccessDetails"></div>

            <div class="cm-success-actions">
                <a href="{$WEB_ROOT}/clientarea.php" class="theme-btn">
                    {if $language == 'french'}Mon espace client{else}Client Area{/if}
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="{$WEB_ROOT}/index.php?rp=/cm-domain-order" class="btn btn-default cm-new-order">
                    {if $language == 'french'}Enregistrer un autre domaine{else}Register another domain{/if}
                </a>
            </div>
        </div>
    </div>

</div><!-- /.cm-order-page -->

<!-- Inline scoped styles for this page -->
<style>
/* ── Layout ────────────────────────────────────────────────────────────── */
.cm-order-page {
    max-width: 760px;
    margin: 0 auto;
    padding: 20px 0 60px;
}

/* ── Step indicator ────────────────────────────────────────────────────── */
.cm-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 40px;
    gap: 0;
}
.cm-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    opacity: 0.4;
    transition: opacity 0.3s;
}
.cm-step.active,
.cm-step.done {
    opacity: 1;
}
.cm-step-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--bg, #f3f7fb);
    border: 2px solid var(--border, #d4dcff);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: var(--text, #445375);
    transition: all 0.3s;
}
.cm-step.active .cm-step-icon {
    background: var(--theme, #236a25);
    border-color: var(--theme, #236a25);
    color: #fff;
    box-shadow: 0 4px 14px rgba(35, 106, 37, 0.35);
}
.cm-step.done .cm-step-icon {
    background: var(--theme2, #ffa31a);
    border-color: var(--theme2, #ffa31a);
    color: #fff;
}
.cm-step span {
    font-size: 12px;
    font-weight: 500;
    color: var(--text, #445375);
    white-space: nowrap;
}
.cm-step-line {
    flex: 1;
    height: 2px;
    background: var(--border, #d4dcff);
    min-width: 40px;
    margin: 0 6px;
    margin-bottom: 22px;
}

/* ── Panel card ─────────────────────────────────────────────────────────── */
.cm-panel {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.07);
    overflow: hidden;
}
.cm-panel-inner {
    padding: 40px;
}
.cm-panel-title {
    font-size: 26px;
    font-weight: 700;
    color: var(--header, #0f0d1d);
    margin-bottom: 6px;
}
.cm-panel-title .cm-tld {
    color: var(--theme, #236a25);
}
.cm-panel-subtitle {
    color: var(--text, #445375);
    margin-bottom: 30px;
}

/* ── Search row ─────────────────────────────────────────────────────────── */
.cm-search-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.cm-search-input-wrap {
    flex: 1;
    position: relative;
    min-width: 220px;
}
.cm-search-input {
    width: 100%;
    height: 52px;
    border: 2px solid var(--border, #d4dcff);
    border-radius: 10px;
    padding: 0 70px 0 16px;
    font-size: 16px;
    font-family: inherit;
    color: var(--header, #0f0d1d);
    background: #fff;
    transition: border-color 0.2s;
    outline: none;
}
.cm-search-input:focus {
    border-color: var(--theme, #236a25);
}
.cm-tld-badge {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 700;
    font-size: 15px;
    color: var(--theme, #236a25);
    pointer-events: none;
}
.cm-search-btn {
    height: 52px;
    padding: 0 28px;
    white-space: nowrap;
    border-radius: 10px !important;
}
.cm-search-hint {
    font-size: 12px;
    color: #888;
    margin-top: 8px;
}

/* ── Availability result ────────────────────────────────────────────────── */
.cm-domain-result {
    margin-top: 24px;
    border: 2px solid var(--border, #d4dcff);
    border-radius: 12px;
    overflow: hidden;
    animation: cmFadeIn 0.3s ease;
}
.cm-domain-result-inner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    flex-wrap: wrap;
}
.cm-domain-result.available {
    border-color: var(--theme, #236a25);
    background: rgba(35, 106, 37, 0.04);
}
.cm-domain-result.taken {
    border-color: #dc3545;
    background: rgba(220, 53, 69, 0.04);
}
.cm-domain-badge {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.cm-domain-result.available .cm-domain-badge {
    background: rgba(35, 106, 37, 0.12);
    color: var(--theme, #236a25);
}
.cm-domain-result.taken .cm-domain-badge {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}
.cm-domain-info {
    flex: 1;
}
.cm-domain-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--header, #0f0d1d);
    margin: 0 0 2px;
}
.cm-domain-status {
    font-size: 13px;
    margin: 0;
}
.cm-domain-result.available .cm-domain-status { color: var(--theme, #236a25); }
.cm-domain-result.taken .cm-domain-status { color: #dc3545; }
.cm-proceed-btn {
    border-radius: 10px !important;
    white-space: nowrap;
}

/* ── Order summary bar ──────────────────────────────────────────────────── */
.cm-order-summary-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg, #f3f7fb);
    border-radius: 10px;
    padding: 12px 18px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.cm-order-summary-bar i { color: var(--theme, #236a25); }
.cm-highlight-domain {
    font-weight: 700;
    color: var(--theme, #236a25);
    margin-left: 4px;
}
.cm-change-domain {
    background: none;
    border: none;
    color: var(--theme2, #ffa31a);
    font-size: 13px;
    cursor: pointer;
    padding: 0;
    margin-left: auto;
}
.cm-change-domain:hover { text-decoration: underline; }

/* ── Form helpers ────────────────────────────────────────────────────────── */
.cm-form-group { margin-bottom: 20px; }
.cm-form-group label { font-weight: 500; margin-bottom: 6px; display: block; }
.cm-required { color: #dc3545; }
.cm-divider { border-color: var(--border, #d4dcff); margin: 28px 0; }
.cm-section-heading {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--header, #0f0d1d);
}
.cm-section-heading i { color: var(--theme, #236a25); margin-right: 6px; }

/* ── Password ────────────────────────────────────────────────────────────── */
.cm-password-wrap { position: relative; }
.cm-password-wrap .form-control { padding-right: 44px; }
.cm-eye-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 0;
}
.cm-eye-btn:hover { color: var(--theme, #236a25); }
.cm-pw-strength {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: -12px;
    margin-bottom: 16px;
}
.cm-pw-bar {
    flex: 1;
    height: 4px;
    border-radius: 4px;
    background: var(--border, #d4dcff);
    transition: background 0.3s, width 0.3s;
}
.cm-pw-bar.weak   { background: #dc3545; width: 33%; }
.cm-pw-bar.fair   { background: var(--theme2, #ffa31a); width: 66%; }
.cm-pw-bar.strong { background: var(--theme, #236a25); width: 100%; }
.cm-pw-label { font-size: 12px; color: #888; min-width: 50px; }

/* ── Terms checkbox ─────────────────────────────────────────────────────── */
.cm-terms-check { margin-bottom: 28px; }
.cm-terms-check .form-check-input { margin-top: 4px; }

/* ── Actions row ────────────────────────────────────────────────────────── */
.cm-form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.cm-submit-btn { min-width: 180px; }
.cm-back-btn   { min-width: 100px; }

/* ── Alert ──────────────────────────────────────────────────────────────── */
.cm-alert {
    padding: 14px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 500;
    border-left: 4px solid;
}
.cm-alert.error   { background: rgba(220,53,69,0.08); color: #842029; border-color: #dc3545; }
.cm-alert.success { background: rgba(35,106,37,0.08); color: var(--theme,#236a25); border-color: var(--theme,#236a25); }

/* ── Success panel ──────────────────────────────────────────────────────── */
.cm-success-panel { text-align: center; padding: 60px 40px; }
.cm-success-icon { font-size: 64px; color: var(--theme, #236a25); margin-bottom: 20px; }
.cm-success-details {
    background: var(--bg, #f3f7fb);
    border-radius: 10px;
    padding: 20px 28px;
    margin: 24px auto;
    max-width: 440px;
    text-align: left;
}
.cm-success-details p { margin: 6px 0; font-size: 14px; }
.cm-success-details strong { color: var(--header, #0f0d1d); }
.cm-success-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 32px;
}
.cm-new-order { border-radius: 10px !important; }

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.cm-spinner { margin-left: 6px; }

/* ── Animations ─────────────────────────────────────────────────────────── */
@keyframes cmFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.cm-panel { animation: cmFadeIn 0.3s ease; }

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 576px) {
    .cm-panel-inner { padding: 24px 18px; }
    .cm-search-row  { flex-direction: column; }
    .cm-search-btn  { width: 100%; justify-content: center; }
    .cm-panel-title { font-size: 20px; }
    .cm-steps       { gap: 0; }
    .cm-step span   { font-size: 11px; }
}
</style>

<script src="{$WEB_ROOT}/templates/{$template}/js/cm-domain-order.js"></script>
