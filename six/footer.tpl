
                </div><!-- /.main-content -->
                {if !$inShoppingCart && $secondarySidebar->hasChildren()}
                    <div class="col-lg-3 col-md-4 sidebar sidebar-secondary">
                        {include file="$template/includes/sidebar.tpl" sidebar=$secondarySidebar}
                    </div>
                {/if}
            <div class="clearfix"></div>
        </div>
    </div>
</section>

<!-- Footer Section Start (exact CAMDigit layout) -->
<footer class="footer-section bg-cover fix" style="background-image: url('{$WEB_ROOT}/templates/{$template}/img/section-bg.webp');">
    <div class="footer-widgets-wrapper">
        <div class="container">
            <div class="row">
                <!-- Column 1: Logo & Contact -->
                <div class="col-xl-3 col-lg-4 col-md-6 wow fadeInUp" data-wow-delay=".2s">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <a href="{$WEB_ROOT}/index.php">
                                <img src="{$WEB_ROOT}/templates/{$template}/img/logo/logo-camdigit-small-small.png" alt="{$companyname}" width="128px" />
                            </a>
                        </div>
                        <div class="footer-content">
                            <p>Your trusted partner for reliable web hosting solutions. Powering websites with speed, security, and 24/7 support.</p>
                            <div class="footer-contact-info">
                                <div class="footer-contact-single d-flex align-items-center">
                                    <div class="icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7293C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.147 21.5901 20.9049 21.7335 20.6408 21.8227C20.3767 21.912 20.0964 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5341 11.19 18.85C8.77383 17.3147 6.72534 15.2662 5.19 12.85C3.49998 10.2412 2.44824 7.27097 2.12 4.18C2.09501 3.90347 2.12787 3.62476 2.21649 3.36162C2.30512 3.09849 2.44756 2.85669 2.63476 2.65162C2.82196 2.44655 3.0498 2.28271 3.30379 2.17052C3.55777 2.05833 3.83233 2.00026 4.11 2H7.11C7.5953 1.99522 8.06579 2.16708 8.43376 2.48353C8.80173 2.79999 9.04208 3.23945 9.11 3.72C9.23662 4.68007 9.47145 5.62273 9.81 6.53C9.94455 6.88792 9.97366 7.27691 9.89391 7.65088C9.81415 8.02485 9.62886 8.36811 9.36 8.64L8.09 9.91C9.51356 12.4135 11.5865 14.4864 14.09 15.91L15.36 14.64C15.6319 14.3711 15.9751 14.1858 16.3491 14.1061C16.7231 14.0263 17.1121 14.0554 17.47 14.19C18.3773 14.5286 19.3199 14.7634 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="content">
                                        <p>(+237) 696 77 00 74</p>
                                    </div>
                                </div>
                                <div class="footer-contact-single d-flex align-items-center">
                                    <div class="icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M22 6L12 13L2 6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="content">
                                        <p>contact@camdigit.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Company Links -->
                <div class="col-xl-2 col-lg-4 col-md-6 wow fadeInUp" data-wow-delay=".4s">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3>Company</h3>
                        </div>
                        <ul class="list-area">
                            <li><a href="{$WEB_ROOT}/index.php"><i class="fa-solid fa-chevrons-right"></i> Home</a></li>
                            <li><a href="{$WEB_ROOT}/contact.php"><i class="fa-solid fa-chevrons-right"></i> Contact Us</a></li>
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> Order</a></li>
                            <li><a href="{$WEB_ROOT}/knowledgebase.php"><i class="fa-solid fa-chevrons-right"></i> Knowledge Base</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Column 3: Hosting Links -->
                <div class="col-xl-2 col-lg-4 col-md-6 wow fadeInUp" data-wow-delay=".6s">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3>Hosting</h3>
                        </div>
                        <ul class="list-area">
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> Shared Hosting</a></li>
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> VPS Hosting</a></li>
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> Dedicated Hosting</a></li>
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> Cloud Hosting</a></li>
                            <li><a href="{$WEB_ROOT}/index.php?rp=/store/shared-hosting"><i class="fa-solid fa-chevrons-right"></i> WordPress Hosting</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Column 4: Support Links -->
                <div class="col-xl-2 col-lg-4 col-md-6 wow fadeInUp" data-wow-delay=".8s">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3>Support</h3>
                        </div>
                        <ul class="list-area">
                            <li><a href="{$WEB_ROOT}/submitticket.php"><i class="fa-solid fa-chevrons-right"></i> Submit Ticket</a></li>
                            <li><a href="{$WEB_ROOT}/knowledgebase.php"><i class="fa-solid fa-chevrons-right"></i> Knowledge Base</a></li>
                            <li><a href="{$WEB_ROOT}/serverstatus.php"><i class="fa-solid fa-chevrons-right"></i> Server Status</a></li>
                            <li><a href="{$WEB_ROOT}/announcements.php"><i class="fa-solid fa-chevrons-right"></i> Announcements</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Column 5: Newsletter -->
                <div class="col-xl-3 col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="1s">
                    <div class="single-footer-widget">
                        <div class="widget-head">
                            <h3>Newsletter</h3>
                        </div>
                        <div class="footer-content">
                            <p>Sign up to get the latest updates and offers.</p>
                            <div class="footer-input">
                                <input type="email" placeholder="Enter Email Address" />
                                <button class="newsletter-btn" type="button">
                                    <i class="fa-regular fa-paper-plane"></i>
                                </button>
                            </div>
                            <div class="social-icon d-flex align-items-center">
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                                <a href="#"><i class="fa-brands fa-youtube"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-wrapper d-flex align-items-center justify-content-between">
                <p class="wow fadeInUp" data-wow-delay=".3s">
                    {lang key="copyrightFooterNotice" year=$date_year company=$companyname}
                </p>
                <ul class="brand-logo d-flex align-items-center wow fadeInUp" data-wow-delay=".5s">
                    <li><a href="#"><img src="{$WEB_ROOT}/templates/{$template}/img/visa-logo.png" alt="Visa" /></a></li>
                    <li><a href="#"><img src="{$WEB_ROOT}/templates/{$template}/img/mastercard-logo.png" alt="Mastercard" /></a></li>
                    <li><a href="#"><img src="{$WEB_ROOT}/templates/{$template}/img/payoneer-logo.png" alt="Payoneer" /></a></li>
                </ul>
                <a href="#" id="scrollUp" class="scroll-up wow fadeInUp" data-wow-delay=".7s">
                    <i class="far fa-arrow-up"></i>
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- WHMCS Required: Fullpage Overlay -->
<div id="fullpage-overlay" class="hidden">
    <div class="outer-wrapper">
        <div class="inner-wrapper">
            <img src="{$WEB_ROOT}/assets/img/overlay-spinner.svg" alt="loading">
            <br>
            <span class="msg"></span>
        </div>
    </div>
</div>

<!-- WHMCS Required: Ajax Modal -->
<div class="modal system-modal fade" id="modalAjax" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content panel-primary">
            <div class="modal-header panel-heading">
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                    <span class="sr-only">{$LANG.close}</span>
                </button>
                <h4 class="modal-title"></h4>
            </div>
            <div class="modal-body panel-body">
                {$LANG.loading}
            </div>
            <div class="modal-footer panel-footer">
                <div class="pull-left loader">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    {$LANG.loading}
                </div>
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">
                    {$LANG.close}
                </button>
                <button type="button" class="btn btn-primary modal-submit">
                    {$LANG.submit}
                </button>
            </div>
        </div>
    </div>
</div>

{include file="$template/includes/generate-password.tpl"}

<!-- Bootstrap 5 — load from theme if WHMCS did not already bundle it (WHMCS 9 bundles it; older versions do not) -->
<script>
if (typeof window.bootstrap === 'undefined') {
    document.write('<scr'+'ipt src="{$WEB_ROOT}/templates/{$template}/js/bootstrap.bundle.min.js"><\/scr'+'ipt>');
}
</script>

<!-- CAMDigit JS Stack -->
<script src="{$WEB_ROOT}/templates/{$template}/js/jquery.nice-select.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/jquery.waypoints.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/jquery.counterup.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/swiper-bundle.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/jquery.meanmenu.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/jquery.magnific-popup.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/wow.min.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/camdigit-main.js"></script>
<script>
(function($) {
    // Scroll to top
    var scrollBtn = document.getElementById('scrollUp');
    if (scrollBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollBtn.classList.add('show');
            } else {
                scrollBtn.classList.remove('show');
            }
        });
        scrollBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Initialize WOW.js
    if (typeof WOW !== 'undefined') {
        new WOW().init();
    }

    // ============ BS3 ↔ BS5 Compatibility Layer ============
    // Template uses BS5 CSS but BS3 JS. Bridge the gaps.

    // --- Fix: BS3 modal adds .in, BS5 CSS expects .show ---
    // Patch $.fn.modal to also add/remove .show alongside .in
    if ($.fn.modal) {
        var _origModal = $.fn.modal;
        $.fn.modal = function(option) {
            var result = _origModal.apply(this, arguments);
            // After BS3 opens a modal, also add .show for BS5 CSS
            if (option === 'show') {
                this.addClass('show');
                this.one('hidden.bs.modal', function() {
                    $(this).removeClass('show');
                });
            }
            return result;
        };
        $.fn.modal.Constructor = _origModal.Constructor;
    }

    // --- Tab switching for pricing section ---
    $(document).on('click', '[data-bs-toggle="tab"]', function(e) {
        e.preventDefault();
        var $this = $(this);
        var target = $this.attr('href') || $this.data('bs-target');
        // Deactivate all tabs in this nav
        $this.closest('.nav').find('.nav-link').removeClass('active').attr('aria-selected', 'false');
        // Activate clicked tab
        $this.addClass('active').attr('aria-selected', 'true');
        // Hide all tab-panes in the parent tab-content
        var $tabContent = $(target).closest('.tab-content');
        $tabContent.children('.tab-pane').removeClass('show active');
        // Show target pane
        $(target).addClass('show active');
    });

    // --- Accordion/Collapse for FAQ section ---
    $(document).on('click', '[data-bs-toggle="collapse"]', function(e) {
        e.preventDefault();
        var $this = $(this);
        var target = $this.data('bs-target') || $this.attr('href');
        var $target = $(target);
        var $parent = $target.data('bs-parent') ? $($target.data('bs-parent')) : null;

        if ($target.hasClass('show')) {
            // Collapse this item
            $target.removeClass('show');
            $this.addClass('collapsed').attr('aria-expanded', 'false');
        } else {
            // If accordion (has parent), collapse siblings first
            if ($parent) {
                $parent.find('.accordion-collapse.show').each(function() {
                    $(this).removeClass('show');
                    $(this).prev('.accordion-header').find('.accordion-button').addClass('collapsed').attr('aria-expanded', 'false');
                });
            }
            // Expand this item
            $target.addClass('show');
            $this.removeClass('collapsed').attr('aria-expanded', 'true');
        }
    });

    $(document).ready(function() {
        // Language dropdown toggle
        var $languageToggle = $('#languageToggle');
        var $languageDropdown = $('#languageDropdown');
        if ($languageToggle.length && $languageDropdown.length) {
            var closeLanguageDropdown = function() {
                $languageDropdown.removeClass('open');
                $languageToggle.removeClass('open').attr('aria-expanded', 'false');
            };

            $languageToggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if ($languageDropdown.hasClass('open')) {
                    closeLanguageDropdown();
                } else {
                    $languageDropdown.addClass('open');
                    $languageToggle.addClass('open').attr('aria-expanded', 'true');
                }
            });

            $languageDropdown.on('click', function(e) {
                e.stopPropagation();
            });

            $(document).on('click.languageDropdown', function() {
                closeLanguageDropdown();
            });
        }

        // Notification bell popover
        if ($('#accountNotifications').length && $.fn.popover) {
            $('#accountNotifications').popover({
                html: true,
                placement: 'bottom',
                container: 'body',
                content: function() {
                    return $('#accountNotificationsContent').length ? $('#accountNotificationsContent').html() : '';
                }
            });
        }
    });

})(jQuery);
</script>

{$footeroutput}

<script>
/* Global .cm domain redirect — covers every domain search input sitewide */
(function () {
    var CM = '{$WEB_ROOT}/order-cm.php';

    function redirectCm(val) {
        val = (val || '').trim();
        if (!/\.cm$/i.test(val)) return false;
        var sld = val.replace(/\.cm$/i, '').toLowerCase();
        if (!sld) return false;
        window.location.href = CM + '?sld=' + encodeURIComponent(sld);
        return true;
    }

    /* Intercept any form that contains input[name="domain"] */
    document.addEventListener('submit', function (e) {
        var input = e.target.querySelector('input[name="domain"]');
        if (input && redirectCm(input.value)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

    /* Also intercept Search-button clicks (covers AJAX-driven domain checker forms
       where the button click is handled before a submit event fires) */
    document.addEventListener('click', function (e) {
        var btn = e.target;
        /* Walk up to find the actual button element */
        while (btn && btn !== document.body) {
            if ((btn.tagName === 'BUTTON' || (btn.tagName === 'INPUT' && btn.type === 'submit'))
                    && btn.id !== 'cmSearchBtn') {
                var form = btn.form || (btn.closest ? btn.closest('form') : null);
                if (form) {
                    var input = form.querySelector('input[name="domain"]');
                    if (input && redirectCm(input.value)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return;
                    }
                }
            }
            btn = btn.parentElement;
        }
    }, true);

}());
</script>

</body>
</html>
