<template>
    <LegalLayout
        kicker="Politique de confidentialité"
        title="Politique de Confidentialité"
        subtitle="Conforme au RGPD, à la Loi sénégalaise n°2008-12 et à la Directive UEMOA N° 02/2015/CM/UEMOA."
        version="1.0"
        updated="Avril 2026">

        <h2>Article 1 — Préambule</h2>
        <p>
            La présente politique de confidentialité (ci-après la « Politique ») définit les conditions dans
            lesquelles GlobalAfrica+, immatriculée au RCCM de Dakar, siège à Dakar (Sénégal), collecte, traite,
            stocke et protège les données à caractère personnel de ses utilisateurs.
        </p>
        <p>
            GlobalAfrica+ est une plateforme numérique panafricaine qui connecte les gouvernements,
            entrepreneurs, investisseurs, porteurs de projets, chercheurs d'emploi et mentors de la diaspora,
            afin de stimuler l'employabilité et le développement économique à travers le continent africain et
            sa diaspora mondiale.
        </p>
        <p>Cette Politique s'applique à l'ensemble des services proposés par GlobalAfrica+ via :</p>
        <ul>
            <li>Le site web <a href="https://www.globalafricaplus.com">www.globalafricaplus.com</a> (PWA)</li>
            <li>L'application mobile native (Android et iOS)</li>
            <li>L'interface USSD/SMS pour les utilisateurs sans smartphone</li>
            <li>Les API mises à disposition des partenaires institutionnels et gouvernementaux</li>
        </ul>

        <h2>Article 2 — Cadre juridique applicable</h2>
        <h3>2.1 Réglementation internationale</h3>
        <ul>
            <li>Le Règlement Général sur la Protection des Données (RGPD) — Règlement (UE) 2016/679 du 27 avril 2016.</li>
            <li>La Convention de l'Union Africaine sur la Cybersécurité et la Protection des Données (Convention de Malabo, 2014).</li>
        </ul>
        <h3>2.2 Réglementation régionale (UEMOA / CEDEAO)</h3>
        <ul>
            <li>L'Acte additionnel A/SA.1/01/10 relatif à la protection des données dans l'espace CEDEAO.</li>
            <li>La Directive N° 02/2015/CM/UEMOA relative à la lutte contre le blanchiment de capitaux et le financement du terrorisme (KYC/AML).</li>
        </ul>
        <h3>2.3 Réglementation nationale (Sénégal)</h3>
        <ul>
            <li>La Loi n°2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel.</li>
            <li>Le Décret n°2008-721 du 30 juin 2008 portant application de la loi n°2008-12.</li>
            <li>La Commission des Données Personnelles (CDP) du Sénégal, autorité de contrôle compétente.</li>
        </ul>

        <h2>Article 3 — Responsable du traitement</h2>
        <ul>
            <li><strong>Raison sociale</strong> : GlobalAfrica+</li>
            <li><strong>Siège social</strong> : Dakar, Sénégal</li>
            <li><strong>Email DPO</strong> : <a href="mailto:dpo@globalafrica.plus">dpo@globalafrica.plus</a></li>
        </ul>
        <p>
            Un Délégué à la Protection des Données (DPO) est désigné et joignable à l'adresse
            <a href="mailto:dpo@globalafrica.plus">dpo@globalafrica.plus</a> pour toute question relative à la
            protection de vos données.
        </p>

        <h2>Article 4 — Données à caractère personnel collectées</h2>

        <h3>4.1 Données d'identification</h3>
        <table>
            <thead>
                <tr><th>Catégorie</th><th>Données collectées</th><th>Obligatoire</th></tr>
            </thead>
            <tbody>
                <tr><td>Identité civile</td><td>Nom, prénom, date et lieu de naissance</td><td>Oui</td></tr>
                <tr><td>Coordonnées</td><td>Email, téléphone (E.164), adresse postale</td><td>Oui (email ou téléphone)</td></tr>
                <tr><td>Localisation</td><td>Pays (ISO 3166-1), ville</td><td>Oui</td></tr>
                <tr><td>Photo</td><td>Avatar / photo de profil</td><td>Non</td></tr>
                <tr><td>Langue</td><td>Français, anglais, portugais, arabe</td><td>Oui</td></tr>
            </tbody>
        </table>

        <h3>4.2 Données de vérification d'identité (eKYC)</h3>
        <p>Conformément à la Directive UEMOA N° 02/2015/CM/UEMOA :</p>
        <ul>
            <li>Copie numérique de CNI, passeport ou permis de conduire (niveaux Vérifié / Certifié)</li>
            <li>Selfie pour vérification faciale via Smile Identity (niveaux Vérifié / Certifié)</li>
            <li>Justificatif de domicile (Certifié)</li>
            <li>Extrait RCCM de moins de 3 mois (entrepreneurs et personnes morales — Certifié)</li>
            <li>Résultat de vérification (accepté/refusé, niveau, expiration)</li>
        </ul>
        <p>
            Les données biométriques sont traitées exclusivement par Smile Identity et ne sont pas conservées
            par GlobalAfrica+ au-delà de la vérification. Seul le résultat est stocké.
        </p>

        <h3>4.3 Données professionnelles et de projet</h3>
        <ul>
            <li>Compétences, expériences, secteurs (matching IA, marketplace de compétences)</li>
            <li>Informations de projet : titre, description, secteur, localisation GPS, budget, montant recherché, emplois visés</li>
            <li>Documents : business plans, études de marché, statuts (chiffrés sur AWS S3)</li>
            <li>Données de mentorat : objectifs, jalons, évaluations, visioconférence</li>
        </ul>

        <h3>4.4 Données financières et transactionnelles</h3>
        <ul>
            <li>Montants d'investissements, devises, taux de change</li>
            <li>Méthode de paiement (Wave, Orange Money, M-Pesa, MTN MoMo, Stripe, PayPal, SEPA/SWIFT) — sans stockage des cartes bancaires</li>
            <li>Références de transaction des passerelles (Flutterwave, Paystack, Stripe)</li>
            <li>Données d'escrow : jalons, montants, statuts, validations</li>
            <li>Simulations d'investissement sauvegardées (portail diaspora)</li>
            <li>Historique des commissions et frais</li>
        </ul>

        <h3>4.5 Données d'utilisation et techniques</h3>
        <ul>
            <li>IP, navigateur, OS, résolution d'écran</li>
            <li>Pages visitées, durée des sessions, parcours</li>
            <li>Géolocalisation (si autorisée) pour la carte interactive</li>
            <li>Préférences de recherche, filtres sauvegardés</li>
            <li>Interactions avec le moteur de recommandation IA</li>
            <li>Données de synchronisation hors-ligne (IndexedDB / SQLite)</li>
            <li>Journaux d'audit des actions sensibles</li>
        </ul>

        <h3>4.6 Données de communication</h3>
        <ul>
            <li>Messages via messagerie interne sécurisée (chiffrement E2E)</li>
            <li>Notifications reçues et lues (email, SMS, push, in-app)</li>
            <li>Historique des interactions avec l'assistant IA conversationnel</li>
        </ul>

        <h2>Article 5 — Finalités du traitement</h2>
        <table>
            <thead>
                <tr><th>Finalité</th><th>Base légale (RGPD)</th></tr>
            </thead>
            <tbody>
                <tr><td>Création et gestion de compte</td><td>Exécution du contrat (Art. 6.1.b)</td></tr>
                <tr><td>Vérification d'identité (eKYC)</td><td>Obligation légale (Art. 6.1.c)</td></tr>
                <tr><td>Matching IA et mise en relation</td><td>Exécution du contrat (Art. 6.1.b)</td></tr>
                <tr><td>Traitement des paiements et escrow</td><td>Exécution du contrat (Art. 6.1.b)</td></tr>
                <tr><td>Lutte anti-blanchiment (LCB-FT)</td><td>Obligation légale (Art. 6.1.c)</td></tr>
                <tr><td>Recommandations IA, scoring de fiabilité</td><td>Intérêt légitime (Art. 6.1.f)</td></tr>
                <tr><td>Communication, messagerie, support</td><td>Exécution du contrat (Art. 6.1.b)</td></tr>
                <tr><td>Formation et mentorat</td><td>Exécution du contrat (Art. 6.1.b)</td></tr>
                <tr><td>Tableau de bord gouvernemental (agrégé)</td><td>Intérêt légitime (Art. 6.1.f)</td></tr>
                <tr><td>Amélioration des services, sécurité</td><td>Intérêt légitime (Art. 6.1.f)</td></tr>
                <tr><td>Marketing (opt-in)</td><td>Consentement (Art. 6.1.a)</td></tr>
            </tbody>
        </table>

        <h2>Article 6 — Bases légales du traitement</h2>
        <ul>
            <li><strong>Consentement (Art. 6.1.a)</strong> : marketing, cookies non essentiels, géolocalisation, biométrie. Retirable à tout moment.</li>
            <li><strong>Exécution du contrat (Art. 6.1.b)</strong> : mise en relation, paiements, mentorat, publication de projets.</li>
            <li><strong>Obligation légale (Art. 6.1.c)</strong> : eKYC, LCB-FT, conservation transactionnelle, déclarations fiscales.</li>
            <li><strong>Intérêt légitime (Art. 6.1.f)</strong> : sécurité, amélioration des services, scoring, recommandations IA, tableaux de bord d'impact.</li>
        </ul>

        <h2>Article 7 — Traitement automatisé et IA</h2>
        <h3>7.1 Matching intelligent</h3>
        <p>
            La plateforme utilise des algorithmes d'IA (NLP + collaborative filtering) pour générer des
            recommandations personnalisées. Score de matching 0-100. Ce traitement ne produit pas de décision
            à effet juridique significatif au sens de l'article 22 du RGPD.
        </p>
        <h3>7.2 Scoring de fiabilité</h3>
        <p>
            Le score est calculé selon cinq critères pondérés : documentation (30%), historique du porteur
            (25%), conformité réglementaire (20%), viabilité financière (15%), validation par des tiers (10%).
            Il influence la visibilité mais ne constitue pas une décision d'exclusion automatique.
        </p>
        <h3>7.3 Vos droits sur le traitement automatisé</h3>
        <ul>
            <li>Droit d'obtenir une explication sur la logique du traitement</li>
            <li>Droit de contester un scoring ou une recommandation</li>
            <li>Droit à l'intervention humaine dans l'évaluation</li>
            <li>Droit de désactiver les recommandations personnalisées dans vos paramètres</li>
        </ul>

        <h2>Article 8 — Destinataires des données</h2>
        <table>
            <thead>
                <tr><th>Destinataire</th><th>Finalité</th></tr>
            </thead>
            <tbody>
                <tr><td>Smile Identity</td><td>Vérification d'identité renforcée (eKYC)</td></tr>
                <tr><td>Flutterwave / Paystack</td><td>Paiements mobile money</td></tr>
                <tr><td>Stripe / PayPal</td><td>Paiements internationaux (diaspora)</td></tr>
                <tr><td>AWS (Cape Town, af-south-1)</td><td>Hébergement cloud et stockage (AES-256)</td></tr>
                <tr><td>Africa's Talking</td><td>Communication SMS et USSD</td></tr>
                <tr><td>Firebase (Google)</td><td>Notifications push mobile</td></tr>
                <tr><td>Autorités compétentes</td><td>Obligations légales (LCB-FT, fiscalité)</td></tr>
                <tr><td>Partenaires gouvernementaux</td><td>Données agrégées et anonymisées uniquement</td></tr>
            </tbody>
        </table>
        <p>
            <strong>GlobalAfrica+ ne vend jamais vos données personnelles à des tiers.</strong> Les données
            partagées avec les sous-traitants sont encadrées par des accords conformes à l'article 28 du RGPD.
        </p>

        <h2>Article 9 — Transferts internationaux de données</h2>
        <h3>9.1 Hébergement principal</h3>
        <p>
            Les données sont hébergées sur AWS Africa au Cap (Afrique du Sud), région af-south-1, pour une
            proximité géographique avec les utilisateurs africains.
        </p>
        <h3>9.2 Garanties pour les transferts</h3>
        <ul>
            <li>Clauses contractuelles types (CCT) approuvées par la Commission européenne (décision 2021/914)</li>
            <li>Chiffrement AES-256 au repos, TLS 1.3 en transit, pseudonymisation des données exportées</li>
            <li>Analyse d'impact des transferts (TIA) réalisée pour chaque nouveau sous-traitant</li>
        </ul>

        <h2>Article 10 — Durée de conservation</h2>
        <table>
            <thead>
                <tr><th>Catégorie</th><th>Durée</th><th>Fondement</th></tr>
            </thead>
            <tbody>
                <tr><td>Compte actif</td><td>Durée de la relation contractuelle</td><td>Contrat</td></tr>
                <tr><td>Compte inactif</td><td>3 ans puis suppression</td><td>Intérêt légitime</td></tr>
                <tr><td>Compte supprimé</td><td>30 jours (réversibilité) puis suppression définitive</td><td>Consentement</td></tr>
                <tr><td>Données KYC / eKYC</td><td>5 ans après fin de relation</td><td>Obligation LCB-FT</td></tr>
                <tr><td>Biométrie (selfie)</td><td>Supprimée immédiatement après vérification</td><td>Minimisation</td></tr>
                <tr><td>Transactionnelles</td><td>10 ans</td><td>Obligation fiscale</td></tr>
                <tr><td>Escrow</td><td>10 ans après clôture</td><td>Obligation légale</td></tr>
                <tr><td>Messages E2E</td><td>2 ans puis archivage chiffré</td><td>Intérêt légitime</td></tr>
                <tr><td>Journaux d'audit</td><td>5 ans</td><td>Obligation légale</td></tr>
                <tr><td>Analytics / navigation</td><td>13 mois (recommandation CNIL/CDP)</td><td>Intérêt légitime</td></tr>
                <tr><td>Marketing (opt-in)</td><td>3 ans après dernier contact actif</td><td>Consentement</td></tr>
            </tbody>
        </table>

        <h2>Article 11 — Sécurité des données</h2>
        <h3>11.1 Mesures techniques</h3>
        <ul>
            <li>Chiffrement AES-256 au repos (AWS S3)</li>
            <li>Chiffrement TLS 1.3 en transit</li>
            <li>Chiffrement E2E pour la messagerie interne (protocole Signal adapté)</li>
            <li>Hachage bcrypt (facteur 12) pour les mots de passe</li>
            <li>Authentification 2FA par OTP SMS/email pour les comptes sensibles</li>
            <li>Tokens JWT à expiration courte (15 min access, 7 jours refresh)</li>
            <li>Verrouillage de compte progressif</li>
            <li>Protection OWASP Top 10 (injection, XSS, CSRF)</li>
            <li>Conformité PCI-DSS via les passerelles (aucune donnée de carte stockée)</li>
            <li>Vérification des webhooks par HMAC-SHA256</li>
            <li>Monitoring anti-fraude en temps réel</li>
        </ul>
        <h3>11.2 Mesures organisationnelles</h3>
        <ul>
            <li>Principe du moindre privilège (RBAC via Spatie Laravel-Permission)</li>
            <li>Formation obligatoire du personnel à la protection des données</li>
            <li>Tests de pénétration trimestriels</li>
            <li>Audit des dépendances (Dependabot + Snyk)</li>
            <li>Analyse statique du code (PHPStan niveau 8, ESLint strict)</li>
            <li>Procédure de gestion d'incidents documentée</li>
            <li>Plan de continuité (PCA) et de reprise d'activité (PRA)</li>
        </ul>
        <h3>11.3 Notification des violations</h3>
        <ul>
            <li>Notification à la CDP Sénégal dans un délai de <strong>72 heures</strong></li>
            <li>Notification aux autorités de contrôle étrangères (CNIL, ICO…) si applicable</li>
            <li>Information des personnes concernées sans délai indû en cas de risque élevé (Art. 34 RGPD)</li>
            <li>Documentation dans le registre des violations</li>
        </ul>

        <h2>Article 12 — Vos droits</h2>
        <table>
            <thead>
                <tr><th>Droit</th><th>Modalités</th></tr>
            </thead>
            <tbody>
                <tr><td>Accès (Art. 15)</td><td>Depuis votre profil ou par email au DPO</td></tr>
                <tr><td>Rectification (Art. 16)</td><td>Directement dans votre profil</td></tr>
                <tr><td>Effacement (Art. 17)</td><td>Depuis les paramètres du compte</td></tr>
                <tr><td>Limitation (Art. 18)</td><td>Par email au DPO</td></tr>
                <tr><td>Portabilité (Art. 20)</td><td>Export JSON/CSV depuis les paramètres</td></tr>
                <tr><td>Opposition (Art. 21)</td><td>Depuis les paramètres ou par email</td></tr>
                <tr><td>Retrait du consentement</td><td>Depuis les paramètres (sans rétroactivité)</td></tr>
                <tr><td>Profilage (Art. 22)</td><td>Désactivation des recommandations IA</td></tr>
                <tr><td>Directives post-mortem</td><td>Par email au DPO</td></tr>
            </tbody>
        </table>
        <p>
            Pour exercer vos droits : section « Paramètres &gt; Vie privée », ou contactez
            <a href="mailto:dpo@globalafrica.plus">dpo@globalafrica.plus</a>. Nous répondons dans un délai d'un
            mois (prolongeable de deux mois en cas de complexité, Art. 12.3 RGPD).
        </p>
        <p>
            En cas de réponse insatisfaisante, vous pouvez saisir la
            <a href="https://www.cdp.sn" target="_blank" rel="noopener">CDP du Sénégal</a> ou l'autorité de
            contrôle de votre pays de résidence (CNIL en France, ICO au Royaume-Uni, etc.).
        </p>

        <h2>Article 13 — Cookies et technologies similaires</h2>
        <h3>13.1 Types de cookies utilisés</h3>
        <ul>
            <li><strong>Essentiels</strong> : authentification, sécurité, préférences linguistiques — consentement non requis</li>
            <li><strong>Fonctionnels</strong> : mémorisation des filtres, mode hors-ligne, thème — 12 mois</li>
            <li><strong>Analytiques</strong> : mesure d'audience — 13 mois, consentement requis</li>
            <li><strong>Marketing</strong> : publicité ciblée — 13 mois, consentement requis</li>
        </ul>
        <h3>13.2 Gestion de vos préférences</h3>
        <p>
            Un bandeau de consentement permet de choisir les catégories lors de la première visite. Vous
            pouvez modifier vos préférences à tout moment depuis « Paramètres &gt; Cookies » ou le lien
            « Gérer mes cookies » en pied de page.
        </p>

        <h2>Article 14 — Protection des mineurs</h2>
        <p>
            Les services s'adressent exclusivement aux personnes majeures (18 ans révolus ou âge légal local).
            Les données de mineurs découvertes seront supprimées et le compte clôturé. Signalez-nous tout
            soupçon à <a href="mailto:dpo@globalafrica.plus">dpo@globalafrica.plus</a>.
        </p>

        <h2>Article 15 — Données USSD et SMS</h2>
        <ul>
            <li>Les sessions USSD expirent après 180 secondes d'inactivité ; aucune donnée n'est conservée localement</li>
            <li>Les SMS transitent par les opérateurs mobiles ; la sécurité du réseau opérateur échappe à GlobalAfrica+</li>
            <li>Les codes OTP sont à usage unique et expirent après 10 minutes</li>
            <li>Le consentement USSD est recueilli lors de l'inscription initiale</li>
        </ul>

        <h2>Article 16 — Analyse d'impact (DPIA)</h2>
        <p>
            Conformément à l'article 35 du RGPD, GlobalAfrica+ réalise des DPIA pour les traitements à risque
            élevé : biométrie eKYC, profilage IA, transactions à grande échelle, transferts internationaux,
            surveillance LCB-FT. Les résultats sont disponibles sur demande au DPO.
        </p>

        <h2>Article 17 — Registre des traitements</h2>
        <p>
            Conformément à l'article 30 du RGPD et à l'article 30 de la Loi n°2008-12, GlobalAfrica+ tient un
            registre des activités de traitement tenu à disposition de la CDP du Sénégal et des autorités de
            contrôle compétentes.
        </p>

        <h2>Article 18 — Modification de la Politique</h2>
        <p>
            GlobalAfrica+ se réserve le droit de modifier la présente Politique. En cas de modification
            substantielle, les utilisateurs sont informés par notification au moins <strong>30 jours</strong>
            avant l'entrée en vigueur. Si la modification affecte un traitement fondé sur le consentement, un
            nouveau consentement sera sollicité.
        </p>

        <h2>Article 19 — Droit applicable</h2>
        <p>
            La présente Politique est régie par le droit sénégalais, sans préjudice des dispositions
            impératives du RGPD pour les utilisateurs résidant dans l'UE. Les litiges non résolus à l'amiable
            sont soumis aux tribunaux compétents de Dakar (Sénégal).
        </p>

        <h2>Article 20 — Contact</h2>
        <ul>
            <li>DPO : <a href="mailto:dpo@globalafrica.plus">dpo@globalafrica.plus</a></li>
            <li>Adresse : GlobalAfrica+, Dakar, Sénégal</li>
        </ul>
    </LegalLayout>
</template>

<script setup>
import LegalLayout from '../../components/LegalLayout.vue';
</script>
