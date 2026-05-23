<?php
/**
 * CamDigit — Terms of Service
 * Upload to: <whmcs_root>/cart-terms.php  (path referenced from checkout.php)
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/whmcs.php';

cd_render_head(
    t('Terms of Service','Conditions générales'),
    t('Terms of <span style="color:var(--theme2,#ffa31a)">Service</span>',
      'Conditions <span style="color:var(--theme2,#ffa31a)">générales</span>'),
    t('The agreement between you and CamDigit.','L\'accord entre vous et CamDigit.')
);
?>

<div class="cd-card" style="max-width:820px;margin:0 auto;line-height:1.8;color:#445375">
    <p class="cd-muted" style="font-size:13px">
        <i class="fa fa-clock"></i> <?= t('Last updated','Dernière mise à jour') ?>: <?= date('F Y') ?>
    </p>

    <h2 style="margin-top:6px"><?= t('1. Services','1. Services') ?></h2>
    <p><?= t('CamDigit provides domain registration, web hosting, email, and ancillary services. Service descriptions on the website are illustrative — actual specifications are listed in your invoice and product page.',
             'CamDigit fournit l\'enregistrement de domaines, l\'hébergement web, l\'email et services associés. Les descriptions sur le site sont illustratives ; les spécifications réelles figurent sur votre facture et fiche produit.') ?></p>

    <h2><?= t('2. Payment & renewal','2. Paiement et renouvellement') ?></h2>
    <p><?= t('Services are invoiced in advance for the billing cycle you choose. Renewal invoices are issued 14 days before the due date. Late renewal may result in suspension or domain expiration.',
             'Les services sont facturés à l\'avance pour le cycle choisi. Les factures de renouvellement sont émises 14 jours avant l\'échéance. Tout retard peut entraîner suspension ou expiration du domaine.') ?></p>

    <h2><?= t('3. 30-day money-back guarantee','3. Garantie 30 jours') ?></h2>
    <p><?= t('Hosting purchases can be refunded in full within 30 days of the initial invoice. Domain registrations, SSL certificates, and add-ons are non-refundable once provisioned.',
             'Les achats d\'hébergement sont remboursables sous 30 jours. Les enregistrements de domaine, certificats SSL et add-ons ne sont pas remboursables une fois activés.') ?></p>

    <h2><?= t('4. Acceptable use','4. Utilisation acceptable') ?></h2>
    <p><?= t('You agree not to use our services to host: phishing, malware, illegal content, child exploitation material, spam, or anything violating Cameroonian law. Violations may result in immediate suspension without refund.',
             'Vous vous engagez à ne pas héberger : phishing, malware, contenu illégal, exploitation de mineurs, spam, ou tout contenu enfreignant la loi camerounaise. Toute violation peut entraîner une suspension immédiate sans remboursement.') ?></p>

    <h2><?= t('5. Domains','5. Domaines') ?></h2>
    <p><?= t('Domain registrations are governed by the policies of the respective registry (CMNIC for .CM, ICANN for gTLDs). You confirm that the WHOIS contact information you provide is accurate.',
             'Les enregistrements de domaines sont régis par les politiques du registre concerné (CMNIC pour .CM, ICANN pour les gTLD). Vous confirmez l\'exactitude des informations WHOIS fournies.') ?></p>

    <h2><?= t('6. Liability','6. Responsabilité') ?></h2>
    <p><?= t('Our liability for any claim is limited to the amount you paid for the affected service in the prior twelve months. We are not liable for indirect, consequential, or business-loss damages.',
             'Notre responsabilité est limitée au montant payé pour le service concerné durant les 12 mois précédents. Nous ne sommes pas responsables des dommages indirects ou de pertes d\'exploitation.') ?></p>

    <h2><?= t('7. Changes','7. Modifications') ?></h2>
    <p><?= t('We may update these terms with 30 days\' notice for material changes. Continuing to use the services after that constitutes acceptance.',
             'Nous pouvons mettre à jour ces conditions avec un préavis de 30 jours pour tout changement majeur. La poursuite de l\'utilisation vaut acceptation.') ?></p>

    <h2><?= t('8. Jurisdiction','8. Juridiction') ?></h2>
    <p><?= t('These terms are governed by Cameroonian law. Disputes will be heard in the courts of Yaoundé.',
             'Les présentes conditions sont régies par le droit camerounais. Les litiges relèvent des tribunaux de Yaoundé.') ?></p>
</div>

<?php cd_render_foot(); ?>
