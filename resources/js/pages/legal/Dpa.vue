<template>
    <LegalLayout
        kicker="Accord de traitement des données"
        title="Accord de Traitement des Données (DPA)"
        subtitle="Data Processing Agreement — conforme à l'article 28 du RGPD et à la Loi sénégalaise n°2008-12."
        version="1.0"
        updated="Avril 2026">

        <h2>Préambule</h2>
        <p>Le présent Accord de Traitement des Données (ci-après le « DPA ») est conclu entre :</p>
        <ul>
            <li>
                <strong>Le Responsable du traitement</strong> : le Client (utilisateur, porteur de projet,
                investisseur, institution gouvernementale) utilisant les services de la plateforme
                GlobalAfrica+ (ci-après le « Responsable » ou le « Client »).
            </li>
            <li>
                <strong>Le Sous-traitant</strong> : GlobalAfrica+, RCCM de Dakar, siège social à Dakar,
                Sénégal (ci-après le « Sous-traitant » ou « GlobalAfrica+ »).
            </li>
        </ul>
        <p>
            Le présent Accord est établi conformément à l'article 28 du RGPD (Règlement UE 2016/679), à la Loi
            sénégalaise n°2008-12 du 25 janvier 2008 et à l'Acte additionnel CEDEAO A/SA.1/01/10.
        </p>

        <h2>Article 1 — Objet</h2>
        <p>
            Le présent DPA définit les obligations du Sous-traitant en matière de protection des données à
            caractère personnel qu'il traite pour le compte du Responsable du traitement dans le cadre de la
            fourniture des services de la plateforme GlobalAfrica+.
        </p>
        <p>
            Ce DPA fait partie intégrante des
            <router-link to="/cgu">Conditions Générales d'Utilisation</router-link> et de la
            <router-link to="/confidentialite">Politique de Confidentialité</router-link> de GlobalAfrica+.
        </p>

        <h2>Article 2 — Description du traitement</h2>
        <table>
            <thead>
                <tr><th>Élément</th><th>Description</th></tr>
            </thead>
            <tbody>
                <tr><td>Objet</td><td>Services de mise en relation, d'investissement, de mentorat et de formation via GlobalAfrica+</td></tr>
                <tr><td>Durée</td><td>Durée de la relation contractuelle + durées de conservation légales</td></tr>
                <tr><td>Nature</td><td>Collecte, enregistrement, stockage, consultation, utilisation, transmission, effacement</td></tr>
                <tr><td>Finalités</td><td>Gestion de comptes, KYC/eKYC, matching IA, transactions, mentorat, analytics, sécurité</td></tr>
                <tr><td>Catégories de personnes</td><td>Entrepreneurs, investisseurs (locaux et diaspora), chercheurs d'emploi, mentors, agents gouvernementaux</td></tr>
                <tr><td>Catégories de données</td><td>Identité, coordonnées, documents KYC, biométrie faciale, projet, transactionnelles, navigation</td></tr>
                <tr><td>Données sensibles</td><td>Biométrie faciale (eKYC via Smile Identity), données financières</td></tr>
            </tbody>
        </table>

        <h2>Article 3 — Obligations du Sous-traitant</h2>
        <p>GlobalAfrica+ s'engage à :</p>
        <ul>
            <li>Traiter les données uniquement sur instruction documentée du Responsable, sauf obligation légale (Art. 28.3.a RGPD)</li>
            <li>Garantir que les personnes autorisées sont soumises à une obligation de confidentialité (Art. 28.3.b)</li>
            <li>Mettre en œuvre les mesures techniques et organisationnelles appropriées (Art. 32 RGPD)</li>
            <li>Respecter les conditions de recours à des sous-traitants ultérieurs (Art. 28.2 et 28.4)</li>
            <li>Aider le Responsable à répondre aux demandes d'exercice des droits (Art. 28.3.e)</li>
            <li>Aider au respect des obligations de sécurité, notification de violation, DPIA (Art. 28.3.f)</li>
            <li>Supprimer ou restituer les données à l'issue de la prestation (Art. 28.3.g)</li>
            <li>Mettre à disposition les informations nécessaires pour démontrer la conformité (Art. 28.3.h)</li>
        </ul>

        <h2>Article 4 — Mesures de sécurité</h2>
        <h3>4.1 Mesures techniques</h3>
        <ul>
            <li>Chiffrement AES-256 au repos (documents sur AWS S3)</li>
            <li>Chiffrement TLS 1.3 en transit</li>
            <li>Chiffrement E2E pour la messagerie interne</li>
            <li>Hachage bcrypt (coût 12) pour les mots de passe</li>
            <li>Authentification multi-facteurs (2FA) par OTP</li>
            <li>Tokens JWT avec expiration courte (15 min access, 7 jours refresh)</li>
            <li>Conformité PCI-DSS via les passerelles de paiement</li>
            <li>Vérification de signature HMAC-SHA256 des webhooks</li>
            <li>Monitoring anti-fraude en temps réel</li>
            <li>Protection OWASP Top 10 (SQLi, XSS, CSRF)</li>
        </ul>
        <h3>4.2 Mesures organisationnelles</h3>
        <ul>
            <li>Principe du moindre privilège (RBAC via Spatie Laravel-Permission)</li>
            <li>Formation obligatoire du personnel à la protection des données</li>
            <li>Tests de pénétration trimestriels</li>
            <li>Audit de sécurité des dépendances (Dependabot + Snyk)</li>
            <li>Journalisation d'audit immuable (5 ans de rétention)</li>
            <li>Plan de continuité (PCA) et plan de reprise (PRA)</li>
        </ul>

        <h2>Article 5 — Sous-traitants ultérieurs</h2>
        <p>
            Le Responsable autorise GlobalAfrica+ à recourir aux sous-traitants ultérieurs listés ci-dessous.
            GlobalAfrica+ informera le Responsable de tout ajout ou remplacement au moins 30 jours à l'avance.
        </p>
        <table>
            <thead>
                <tr><th>Sous-traitant</th><th>Pays / Région</th><th>Traitement</th><th>Garanties</th></tr>
            </thead>
            <tbody>
                <tr><td>AWS (Amazon)</td><td>Afrique du Sud (Cape Town)</td><td>Hébergement cloud, S3</td><td>CCT + AES-256</td></tr>
                <tr><td>Smile Identity</td><td>Nigeria / Kenya</td><td>Vérification d'identité (eKYC)</td><td>DPA + suppression post-vérification</td></tr>
                <tr><td>Flutterwave</td><td>Nigeria</td><td>Paiements mobile money</td><td>PCI-DSS + CCT</td></tr>
                <tr><td>Paystack</td><td>Nigeria</td><td>Paiements mobile money</td><td>PCI-DSS + CCT</td></tr>
                <tr><td>Stripe</td><td>USA / Irlande</td><td>Paiements internationaux</td><td>PCI-DSS + CCT</td></tr>
                <tr><td>PayPal</td><td>USA / Luxembourg</td><td>Paiements internationaux</td><td>PCI-DSS + CCT</td></tr>
                <tr><td>Africa's Talking</td><td>Kenya</td><td>SMS, USSD, notifications</td><td>DPA + CCT</td></tr>
                <tr><td>Google (Firebase)</td><td>USA / UE</td><td>Notifications push</td><td>CCT + DPA Google Cloud</td></tr>
                <tr><td>Amazon SES / Mailgun</td><td>USA / UE</td><td>Email transactionnel</td><td>CCT + DPA</td></tr>
            </tbody>
        </table>
        <p>
            Chaque sous-traitant ultérieur est lié par un accord offrant un niveau de protection équivalent au
            présent DPA.
        </p>

        <h2>Article 6 — Transferts internationaux</h2>
        <p>
            Les transferts vers des pays sans décision d'adéquation sont encadrés par :
        </p>
        <ul>
            <li>Clauses Contractuelles Types (CCT) approuvées par la Commission européenne (décision 2021/914)</li>
            <li>Mesures techniques supplémentaires (chiffrement, pseudonymisation)</li>
            <li>Analyse d'impact des transferts (TIA) documentée pour chaque sous-traitant</li>
        </ul>
        <p>L'hébergement principal est situé sur AWS Africa au Cap (Afrique du Sud), région af-south-1.</p>

        <h2>Article 7 — Notification des violations</h2>
        <p>En cas de violation de données à caractère personnel, GlobalAfrica+ s'engage à :</p>
        <ul>
            <li>Notifier le Responsable dans un délai de <strong>48 heures</strong> après prise de connaissance</li>
            <li>Fournir les informations requises par l'article 33.3 du RGPD (nature, catégories, mesures)</li>
            <li>Coopérer pour la notification aux autorités (CDP Sénégal, CNIL, etc.) dans les 72 heures</li>
            <li>Documenter l'incident dans le registre des violations</li>
        </ul>

        <h2>Article 8 — Droits des personnes concernées</h2>
        <p>
            GlobalAfrica+ assiste le Responsable pour répondre aux demandes d'exercice des droits (accès,
            rectification, effacement, portabilité, opposition, limitation) dans un délai de
            <strong>5 jours ouvrés</strong>. Les outils intégrés (« Paramètres &gt; Vie privée ») permettent
            l'exercice direct de la plupart de ces droits.
        </p>

        <h2>Article 9 — Audits et inspections</h2>
        <p>
            Le Responsable a le droit de réaliser ou faire réaliser des audits pour vérifier la conformité de
            GlobalAfrica+ au présent DPA, sous réserve de :
        </p>
        <ul>
            <li>Un préavis de 30 jours calendaires</li>
            <li>Un périmètre raisonnable et proportionné</li>
            <li>La non-perturbation des opérations courantes</li>
            <li>Le respect de la confidentialité des informations de GlobalAfrica+ et des autres clients</li>
        </ul>
        <p>
            GlobalAfrica+ met également à disposition les rapports de tests de pénétration et les
            certifications de conformité de ses sous-traitants sur demande.
        </p>

        <h2>Article 10 — Sort des données en fin de contrat</h2>
        <p>À l'expiration ou la résiliation du contrat :</p>
        <ul>
            <li>Restitution dans un format structuré (JSON/CSV) dans un délai de 30 jours</li>
            <li>Suppression irréversible après restitution (ou sur instruction), sauf obligation légale de conservation</li>
            <li>Un certificat de suppression est délivré sur demande</li>
            <li>Les données soumises à des obligations légales (KYC : 5 ans, transactions : 10 ans) sont conservées en accès restreint</li>
        </ul>

        <h2>Article 11 — Responsabilité et indemnisation</h2>
        <p>
            Chaque partie est responsable des dommages causés par un traitement non conforme au RGPD, à la Loi
            n°2008-12 ou au présent DPA. La responsabilité de GlobalAfrica+ est limitée au montant des sommes
            perçues au titre du contrat au cours des 12 mois précédant l'événement générateur, sauf dol ou
            faute lourde.
        </p>

        <h2>Article 12 — Durée</h2>
        <p>
            Le présent DPA entre en vigueur à la date d'acceptation des CGU par le Client et reste en vigueur
            tant que GlobalAfrica+ traite des données pour son compte. Les obligations de confidentialité et
            de sécurité survivent à la résiliation du DPA.
        </p>

        <h2>Article 13 — Droit applicable</h2>
        <p>
            Le présent DPA est régi par le droit sénégalais, sans préjudice des dispositions impératives du
            RGPD pour les personnes concernées résidant dans l'Union européenne. Les litiges seront soumis
            aux tribunaux compétents de Dakar (Sénégal).
        </p>

        <h2>Signatures</h2>
        <p>
            L'acceptation des CGU lors de l'inscription sur la plateforme vaut signature électronique du
            présent DPA par le Client, conformément à la législation sénégalaise sur la signature électronique
            et au règlement eIDAS pour les utilisateurs européens.
        </p>
    </LegalLayout>
</template>

<script setup>
import LegalLayout from '../../components/LegalLayout.vue';
</script>
