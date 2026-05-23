<?php
/**
 * CamDigit — Privacy policy
 * Upload to: <whmcs_root>/privacy.php
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

cd_render_head(
    t('Privacy Policy','Politique de confidentialité'),
    t('Privacy <span style="color:var(--theme2,#ffa31a)">Policy</span>',
      'Politique de <span style="color:var(--theme2,#ffa31a)">confidentialité</span>'),
    t('How we collect, use, and protect your data.','Comment nous collectons, utilisons et protégeons vos données.')
);
?>

<div class="cd-card" style="max-width:820px;margin:0 auto;line-height:1.8;color:#445375">
    <p class="cd-muted" style="font-size:13px">
        <i class="fa fa-clock"></i> <?= t('Last updated','Dernière mise à jour') ?>:
        <?= date('F Y') ?>
    </p>

    <h2 style="margin-top:6px"><?= t('1. Who we are','1. Qui nous sommes') ?></h2>
    <p><?= t('CamDigit ("we", "our", "us") operates the website at',
             'CamDigit (« nous », « notre ») exploite le site') ?>
        <a href="<?= SITE_URL ?>"><?= SITE_URL ?></a>
        <?= t('and provides domain registration, web hosting, and related services.',
              'et fournit des services d\'enregistrement de domaine, d\'hébergement et services connexes.') ?>
    </p>

    <h2><?= t('2. What we collect','2. Données collectées') ?></h2>
    <ul>
        <li><strong><?= t('Account info','Compte') ?>:</strong> <?= t('name, email, address, phone, password (hashed).',
            'nom, email, adresse, téléphone, mot de passe (haché).') ?></li>
        <li><strong><?= t('Billing','Facturation') ?>:</strong> <?= t('invoices, payment method tokens (never raw card numbers).',
            'factures, jetons de paiement (jamais de numéros de carte bruts).') ?></li>
        <li><strong><?= t('Domain WHOIS','WHOIS') ?>:</strong> <?= t('contact details transmitted to registries as required by ICANN/CMNIC.',
            'coordonnées transmises aux registres conformément à ICANN/CMNIC.') ?></li>
        <li><strong><?= t('Technical','Technique') ?>:</strong> <?= t('IP, browser, session cookies for login and cart.',
            'IP, navigateur, cookies de session pour la connexion et le panier.') ?></li>
    </ul>

    <h2><?= t('3. How we use it','3. Utilisation') ?></h2>
    <ul>
        <li><?= t('Deliver and renew the services you order.','Fournir et renouveler les services commandés.') ?></li>
        <li><?= t('Send invoices, renewal reminders, and security notices.','Envoyer factures, rappels et notifications de sécurité.') ?></li>
        <li><?= t('Provide bilingual customer support.','Fournir un support client bilingue.') ?></li>
        <li><?= t('Comply with legal obligations and prevent fraud.','Respecter les obligations légales et prévenir la fraude.') ?></li>
    </ul>

    <h2><?= t('4. Sharing','4. Partage') ?></h2>
    <p><?= t('We share data only with: domain registries (for WHOIS), payment processors (MTN MoMo, Orange Money, Stripe, PayPal, banks), and law-enforcement when legally required.',
             'Nous partageons les données uniquement avec : registres de domaines, prestataires de paiement (MTN MoMo, Orange Money, Stripe, PayPal, banques) et les autorités quand la loi l\'exige.') ?></p>

    <h2><?= t('5. Your rights','5. Vos droits') ?></h2>
    <p><?= t('You can access, correct, or delete your data at any time from your account dashboard, or by contacting',
             'Vous pouvez accéder, rectifier ou supprimer vos données depuis votre tableau de bord, ou en contactant') ?>
        <a href="mailto:privacy@camdigit.com">privacy@camdigit.com</a>.
    </p>

    <h2><?= t('6. Security','6. Sécurité') ?></h2>
    <p><?= t('All traffic is encrypted with TLS. Passwords are hashed with bcrypt. Backups are encrypted at rest. We rate-limit logins and CSRF-protect every form.',
             'Tout le trafic est chiffré en TLS. Les mots de passe sont hachés (bcrypt). Les sauvegardes sont chiffrées. Connexions limitées et formulaires protégés CSRF.') ?></p>

    <h2><?= t('7. Contact','7. Contact') ?></h2>
    <p><?= t('Questions? Email us at','Questions ? Écrivez-nous à') ?>
        <a href="mailto:privacy@camdigit.com">privacy@camdigit.com</a>
        <?= t('or open a ticket from your dashboard.','ou ouvrez un ticket depuis votre tableau de bord.') ?></p>
</div>

<?php cd_render_foot(); ?>
