<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Header -->
        <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">Bonjour, {{ auth.user?.name }} 👋</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    Vue active :
                    <span class="inline-flex items-center gap-1.5 ml-1 px-2 py-0.5 text-xs rounded-md font-semibold"
                          :style="{ backgroundColor: activeBg, color: activeFg }">
                        <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: activeFg }"></span>
                        {{ activeRoleLabel }}
                    </span>
                    <span v-if="auth.roleObjects.length > 1" class="text-xs text-slate-400 dark:text-slate-500 ml-2">
                        ({{ auth.roleObjects.length }} rôles au total)
                    </span>
                </p>
            </div>
            <div class="flex gap-2">
                <!-- Role switcher (if multi-role) -->
                <select v-if="auth.roleObjects.length > 1" @change="switchRole($event.target.value)"
                    class="text-sm px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option v-for="r in auth.roleObjects" :key="r.slug" :value="r.slug" :selected="r.slug === auth.activeRole">
                        {{ LABELS[r.slug] || r.name }}
                    </option>
                </select>
                <router-link to="/profil" class="text-sm font-semibold px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
                    Mes profils
                </router-link>
                <router-link to="/profil/ajouter-role" class="text-sm font-semibold px-3 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                    + Rôle
                </router-link>
            </div>
        </header>

        <!-- KYC Alert Banner -->
        <KycBanner />

        <!-- Subscription banner (free plan) -->
        <div v-if="auth.isFreePlan" class="bg-gradient-to-r from-violet-50 to-indigo-50 dark:from-violet-900/30 dark:to-indigo-900/30 border border-violet-200 dark:border-violet-500/40 rounded-2xl p-5 mb-8 flex items-center gap-4">
            <div class="text-3xl">🔒</div>
            <div class="flex-1">
                <div class="font-semibold text-violet-900 dark:text-violet-200">
                    Plan Gratuit — Fonctionnalités limitées
                </div>
                <p class="text-xs text-violet-700 dark:text-violet-300 mt-1">
                    Publiez des projets, postulez aux offres, accédez au mentorat et bien plus en souscrivant à un pack.
                    <strong>Garantie satisfait ou remboursé 30 jours.</strong>
                </p>
            </div>
            <router-link to="/tarifs"
                class="px-4 py-2 rounded-md bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold shrink-0">
                Voir les packs
            </router-link>
        </div>

        <!-- Completion banner -->
        <div v-if="currentCompletion < 100" class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-500/40 rounded-2xl p-5 mb-8 flex items-center gap-4">
            <div class="text-3xl">✨</div>
            <div class="flex-1">
                <div class="font-semibold text-amber-900 dark:text-amber-200">
                    Complétez votre profil {{ activeRoleLabel.toLowerCase() }} ({{ currentCompletion }}%)
                </div>
                <div class="h-1.5 bg-white dark:bg-slate-800 rounded-full overflow-hidden mt-2 mb-1">
                    <div class="h-full bg-amber-500 transition-all" :style="{ width: currentCompletion + '%' }"></div>
                </div>
                <p class="text-xs text-amber-800 dark:text-amber-300">Un profil complet est essentiel pour être matché par l'IA.</p>
            </div>
            <router-link :to="`/profil/${auth.activeRole}`"
                class="px-4 py-2 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shrink-0">
                Compléter
            </router-link>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-12 text-center">Chargement du tableau de bord…</div>

        <template v-else>
            <!-- KPIs row -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                <div v-for="kpi in kpis" :key="kpi.label"
                    class="bg-white dark:bg-slate-800 border rounded-2xl p-4 relative"
                    :class="kpi.alert ? 'border-rose-200 dark:border-rose-500/40 bg-rose-50/50 dark:bg-rose-900/20' : 'border-slate-100 dark:border-slate-700'">
                    <span v-if="kpi.alert" class="absolute top-3 right-3 flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                    </span>
                    <div class="text-xl mb-1">{{ kpi.icon }}</div>
                    <div class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">{{ kpi.value }}</div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-0.5">{{ kpi.label }}</div>
                </div>
            </div>

            <!-- Common info row -->
            <div class="grid md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wider">Niveau KYC</div>
                    <div class="mt-1.5 text-xl font-bold capitalize text-slate-900 dark:text-slate-100">{{ kycLabel }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wider">Pays</div>
                    <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-slate-100">{{ common.country || '—' }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wider">Diaspora</div>
                    <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-slate-100">{{ common.is_diaspora ? 'Oui' : 'Non' }}</div>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5">
                    <div class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wider">Membre depuis</div>
                    <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-slate-100">{{ common.member_since || '—' }}</div>
                </div>
            </div>

            <!-- Current plan info -->
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-5 mb-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl">{{ auth.isFreePlan ? '🆓' : '⭐' }}</span>
                    <div>
                        <div class="text-xs uppercase text-slate-500 dark:text-slate-400 tracking-wider">Mon abonnement</div>
                        <div class="font-bold capitalize text-slate-900 dark:text-slate-100">Pack {{ planLabel }}</div>
                    </div>
                </div>
                <router-link v-if="auth.isFreePlan" to="/tarifs"
                    class="text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300">
                    Passer à un pack payant →
                </router-link>
                <span v-else class="text-xs font-semibold px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                    Actif
                </span>
            </div>

            <!-- ============================================ -->
            <!-- ENTREPRENEUR-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'entrepreneur'">
                <!-- Funding progress -->
                <div v-if="roleData.total_needed > 0" class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/30 border border-emerald-100 dark:border-emerald-500/40 rounded-2xl p-6 mb-8">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-emerald-900 dark:text-emerald-200">Progression du financement global</h3>
                        <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ roleData.funding_progress }}%</span>
                    </div>
                    <div class="h-3 bg-white dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full transition-all"
                            :style="{ width: Math.min(roleData.funding_progress, 100) + '%' }"></div>
                    </div>
                    <div class="flex justify-between text-xs text-emerald-700 dark:text-emerald-300 mt-2">
                        <span>Levé : {{ fmtMoney(roleData.total_raised) }}</span>
                        <span>Objectif : {{ fmtMoney(roleData.total_needed) }}</span>
                    </div>
                </div>

                <!-- Formalization banner (for informal entrepreneurs) -->
                <div v-if="roleData.legal_status === 'informal' || (!roleData.legal_status && roleData.formalization)"
                    class="bg-gradient-to-r from-teal-50 to-emerald-50 dark:from-teal-900/30 dark:to-emerald-900/30 border border-teal-200 dark:border-teal-500/40 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="text-3xl">📜</div>
                    <div class="flex-1">
                        <div class="font-semibold text-teal-900 dark:text-teal-200">
                            <template v-if="roleData.formalization">
                                Formalisation : {{ roleData.formalization.completion }}% ({{ roleData.formalization.completed }} / {{ roleData.formalization.total_steps }} étapes)
                            </template>
                            <template v-else>
                                Formalisez votre entreprise pour accéder à plus d'opportunités
                            </template>
                        </div>
                        <p class="text-xs text-teal-700 dark:text-teal-300 mt-1">Guide pas-à-pas adapté à votre pays, templates de business plan gratuits et accès au micro-crédit.</p>
                        <div v-if="roleData.formalization" class="h-1.5 bg-white dark:bg-slate-800 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-teal-500 rounded-full transition-all" :style="{ width: roleData.formalization.completion + '%' }"></div>
                        </div>
                    </div>
                    <router-link to="/formalisation/mon-parcours"
                        class="px-4 py-2 rounded-md bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold shrink-0">
                        {{ roleData.formalization?.completed > 0 ? 'Continuer' : 'Commencer' }}
                    </router-link>
                </div>

                <!-- Action cards -->
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Actions rapides</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard :title="auth.isFreePlan ? '🔒 Nouveau projet' : 'Nouveau projet'" icon="🚀"
                        :text="auth.isFreePlan ? 'Souscrivez à un pack pour publier vos projets.' : 'Publiez un projet et commencez à lever des fonds.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Créer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/projets/nouveau'" />
                    <DashCard title="Mes projets" icon="📊" text="Suivez les vues, investissements et messages reçus." cta="Voir" to="/projets/mes-projets" />
                    <DashCard title="Hub Formalisation" icon="📜"
                        text="Guide pas-à-pas, business plan, micro-crédit."
                        cta="Accéder" to="/formalisation" />
                    <DashCard title="Business Plans" icon="📝"
                        text="Templates gratuits adaptés aux réalités africaines."
                        cta="Voir" to="/formalisation/business-plans" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Trouver un mentor' : 'Trouver un mentor'" icon="🎓"
                        :text="auth.isFreePlan ? 'Souscrivez pour demander un accompagnement personnalisé.' : 'Connectez-vous avec un expert pour vous accompagner.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Explorer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/mentorat'" />
                    <DashCard title="Mon profil" icon="⚙️" text="Complétez votre profil entrepreneur et statut juridique." cta="Éditer" to="/profil/entrepreneur" />
                </div>

                <!-- Recent projects -->
                <div v-if="roleData.recent_projects?.length">
                    <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Mes derniers projets</h2>
                    <div class="space-y-3">
                        <router-link v-for="p in roleData.recent_projects" :key="p.id"
                            :to="`/projets/${p.slug}`"
                            class="flex items-center justify-between bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-xl p-4 hover:border-emerald-200 dark:hover:border-emerald-500/60 transition group">
                            <div class="flex items-center gap-3">
                                <span v-if="p.category" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    :style="{ backgroundColor: p.category.color + '20', color: p.category.color }">
                                    {{ p.category.name }}
                                </span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 group-hover:text-emerald-700 dark:group-hover:text-emerald-400">{{ p.title }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="projectStatusClass(p.status)">{{ projectStatusLabel(p.status) }}</span>
                                <span>{{ p.views_count || 0 }} vues</span>
                                <span>{{ p.followers_count || 0 }} ❤️</span>
                            </div>
                        </router-link>
                    </div>
                </div>

                <!-- Active mentorships -->
                <div v-if="roleData.active_mentorships?.length" class="mt-8">
                    <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Mentorats en cours</h2>
                    <div class="grid md:grid-cols-3 gap-4">
                        <div v-for="m in roleData.active_mentorships" :key="m.id"
                            class="bg-violet-50 dark:bg-violet-900/30 border border-violet-100 dark:border-violet-500/40 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-full bg-violet-200 dark:bg-violet-800 flex items-center justify-center text-xs font-bold text-violet-700 dark:text-violet-200">
                                    {{ m.mentor?.name?.charAt(0) || '?' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-slate-900 dark:text-slate-100">{{ m.mentor?.name }}</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ m.mentor?.country }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="m.status === 'accepted' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300'">
                                {{ m.status === 'accepted' ? 'En cours' : 'En attente' }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ============================================ -->
            <!-- INVESTOR-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'investor'">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Actions rapides</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard title="Explorer les projets" icon="🔎" text="Découvrez des projets à fort potentiel." cta="Explorer" to="/projets" />
                    <DashCard title="Simulateur diaspora" icon="🧮" text="Estimez impact et rendement de vos investissements." cta="Lancer" to="/diaspora" />
                    <DashCard title="Matching IA" icon="🤖" text="Recevez des suggestions personnalisées." cta="Bientôt" />
                </div>

                <!-- Recent investments -->
                <div v-if="roleData.recent_investments?.length">
                    <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Derniers investissements</h2>
                    <div class="space-y-3">
                        <div v-for="inv in roleData.recent_investments" :key="inv.id"
                            class="flex items-center justify-between bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-xl p-4">
                            <div class="flex items-center gap-3">
                                <router-link v-if="inv.project" :to="`/projets/${inv.project.slug}`"
                                    class="font-semibold text-blue-700 dark:text-blue-400 hover:underline">
                                    {{ inv.project.title }}
                                </router-link>
                                <span v-if="inv.project?.category" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    :style="{ backgroundColor: inv.project.category.color + '20', color: inv.project.category.color }">
                                    {{ inv.project.category.name }}
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ fmtMoney(inv.amount) }}</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="invStatusClass(inv.status)">{{ invStatusLabel(inv.status) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ============================================ -->
            <!-- MENTOR-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'mentor'">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Actions rapides</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard :title="auth.isFreePlan ? '🔒 Mes mentorats' : 'Mes mentorats'" icon="📨"
                        :text="auth.isFreePlan ? 'Souscrivez à un pack pour accepter des demandes de mentorat.' : 'Gérez vos demandes et mentorats en cours.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Voir'"
                        :to="auth.isFreePlan ? '/tarifs' : '/mentorat/mes-mentorats'"
                        :alert="!auth.isFreePlan && (roleData.pending_requests?.length || 0) > 0" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Mes disponibilités' : 'Mes disponibilités'" icon="📅"
                        :text="auth.isFreePlan ? 'Souscrivez pour paramétrer vos créneaux.' : 'Paramétrez vos créneaux de mentorat.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Configurer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/mentorat/mes-mentorats'" />
                    <DashCard title="Mon profil mentor" icon="⭐" text="Complétez votre profil pour attirer plus de mentees." cta="Éditer"
                        :to="`/profil/mentor`" />
                </div>

                <!-- Pending requests -->
                <div v-if="roleData.pending_requests?.length" class="mb-8">
                    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                        Demandes en attente
                        <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-semibold">
                            {{ roleData.pending_requests.length }}
                        </span>
                    </h2>
                    <div class="space-y-3">
                        <div v-for="m in roleData.pending_requests" :key="m.id"
                            class="flex items-center justify-between bg-amber-50 border border-amber-100 rounded-xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-amber-200 flex items-center justify-center text-sm font-bold text-amber-700">
                                    {{ m.mentee?.name?.charAt(0) || '?' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">{{ m.mentee?.name }}</div>
                                    <div class="text-xs text-slate-500">{{ m.mentee?.country }} · {{ m.skill?.name || 'Général' }}</div>
                                </div>
                            </div>
                            <router-link to="/mentorat/mes-mentorats"
                                class="text-sm font-semibold text-amber-700 hover:text-amber-800">
                                Répondre →
                            </router-link>
                        </div>
                    </div>
                </div>

                <!-- Active mentorships -->
                <div v-if="roleData.active_mentorships?.length">
                    <h2 class="text-xl font-bold mb-4">Mentorats actifs</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div v-for="m in roleData.active_mentorships" :key="m.id"
                            class="bg-white border border-slate-100 rounded-xl p-4">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center text-sm font-bold text-violet-700">
                                    {{ m.mentee?.name?.charAt(0) || '?' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">{{ m.mentee?.name }}</div>
                                    <div class="text-xs text-slate-500">{{ m.mentee?.country }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                <span v-if="m.skill">🏷️ {{ m.skill.name }}</span>
                                <span>💬 {{ m.sessions_count || 0 }} session(s)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ============================================ -->
            <!-- JOBSEEKER-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'jobseeker'">
                <!-- Alert: accepted applications -->
                <div v-if="roleData.accepted_applications > 0"
                    class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="text-3xl">🎉</div>
                    <div class="flex-1">
                        <div class="font-semibold text-emerald-900">
                            {{ roleData.accepted_applications }} candidature(s) acceptée(s) !
                        </div>
                        <p class="text-xs text-emerald-700 mt-1">Des entrepreneurs ont retenu votre profil.</p>
                    </div>
                    <router-link to="/emploi/mes-candidatures"
                        class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shrink-0">
                        Voir mes candidatures
                    </router-link>
                </div>

                <!-- Top skills -->
                <div v-if="roleData.top_skills?.length" class="mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Mes compétences</h3>
                        <router-link to="/emploi/mes-competences" class="text-xs font-semibold text-amber-600 hover:underline">
                            Gérer →
                        </router-link>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="sk in roleData.top_skills" :key="sk.id"
                            class="text-xs px-3 py-1.5 rounded-full font-medium"
                            :class="levelColor(sk.pivot?.level)">
                            {{ sk.name }}
                            <span class="text-[10px] opacity-70 ml-1">{{ levelLabel(sk.pivot?.level) }}</span>
                        </span>
                    </div>
                </div>

                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Actions rapides</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard title="Offres d'emploi" icon="💼"
                        text="Explorez les projets qui recrutent sur le continent."
                        cta="Explorer" to="/emploi" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Postuler aux offres' : 'Mes candidatures'" icon="📨"
                        :text="auth.isFreePlan ? 'Souscrivez à un pack pour postuler aux offres.' : 'Suivez l\'état de vos candidatures envoyées.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Voir'"
                        :to="auth.isFreePlan ? '/tarifs' : '/emploi/mes-candidatures'"
                        :alert="!auth.isFreePlan && (roleData.total_applications || 0) > 0" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Mes compétences' : 'Mes compétences'" icon="🛠️"
                        :text="auth.isFreePlan ? 'Souscrivez pour gérer vos compétences et activer le matching IA.' : 'Gérez vos compétences techniques pour le matching IA.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Gérer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/emploi/mes-competences'" />
                    <DashCard title="Mon profil" icon="📄"
                        text="Complétez votre CV, formation et expériences."
                        cta="Éditer" :to="`/profil/jobseeker`" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Trouver un mentor' : 'Trouver un mentor'" icon="🎓"
                        :text="auth.isFreePlan ? 'Souscrivez pour demander un accompagnement.' : 'Faites-vous accompagner par un expert du continent.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Explorer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/mentorat'" />
                    <DashCard title="Portail Emploi" icon="🌍"
                        text="Vue publique de toutes les offres d'emploi."
                        cta="Voir" to="/emploi" />
                </div>

                <!-- Recent applications -->
                <div v-if="roleData.recent_applications?.length">
                    <h2 class="text-xl font-bold mb-4">Mes dernières candidatures</h2>
                    <div class="space-y-3">
                        <router-link v-for="a in roleData.recent_applications" :key="a.id"
                            to="/emploi/mes-candidatures"
                            class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-4 hover:border-amber-200 transition group">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="appStatusClass(a.status)">{{ appStatusLabel(a.status) }}</span>
                                <span class="font-semibold group-hover:text-amber-700">{{ a.project?.title }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-slate-500">
                                <span v-if="a.project?.category" class="text-xs"
                                    :style="{ color: a.project.category.color }">{{ a.project.category.name }}</span>
                                <span class="text-xs">{{ a.role_applied }}</span>
                            </div>
                        </router-link>
                    </div>
                </div>
            </template>

            <!-- ============================================ -->
            <!-- GOVERNMENT-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'government'">
                <!-- Alert: pending applications -->
                <div v-if="roleData.pending_reviews?.length"
                    class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="text-3xl">📨</div>
                    <div class="flex-1">
                        <div class="font-semibold text-amber-900">
                            {{ roleData.pending_reviews.length }} candidature(s) en attente de revue
                        </div>
                        <p class="text-xs text-amber-700 mt-1">Des entrepreneurs ont candidaté à vos appels.</p>
                    </div>
                    <router-link to="/gouvernement/mes-appels"
                        class="px-4 py-2 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shrink-0">
                        Voir les candidatures
                    </router-link>
                </div>

                <!-- Extra KPIs row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-sky-700">{{ fmtMoney(roleData.total_budget || 0) }}</div>
                        <div class="text-[11px] uppercase tracking-wider text-sky-600 mt-1">Budget total alloué</div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-emerald-700">{{ roleData.jobs_in_country || 0 }}</div>
                        <div class="text-[11px] uppercase tracking-wider text-emerald-600 mt-1">Emplois visés (pays)</div>
                    </div>
                    <div class="bg-violet-50 border border-violet-100 rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-violet-700">{{ roleData.awarded_calls || 0 }}</div>
                        <div class="text-[11px] uppercase tracking-wider text-violet-600 mt-1">Appels attribués</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 text-center">
                        <div class="text-2xl font-black text-slate-700">{{ roleData.my_zones_count || 0 }}</div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-1">Zones ZES</div>
                    </div>
                </div>

                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Actions rapides</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard :title="auth.isFreePlan ? '🔒 Mes appels à projets' : 'Mes appels à projets'" icon="🏛️"
                        :text="auth.isFreePlan ? 'Souscrivez à un pack pour publier des appels à projets.' : 'Créez, publiez et gérez vos appels à projets publics.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Gérer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/gouvernement/mes-appels'"
                        :alert="!auth.isFreePlan && (roleData.draft_calls || 0) > 0" />
                    <DashCard :title="auth.isFreePlan ? '🔒 Zones économiques' : 'Zones économiques'" icon="🗺️"
                        :text="auth.isFreePlan ? 'Souscrivez pour gérer vos ZES et attirer les investissements.' : 'Gérez les ZES de votre territoire avec avantages et secteurs cibles.'"
                        :cta="auth.isFreePlan ? 'Voir les packs' : 'Gérer'"
                        :to="auth.isFreePlan ? '/tarifs' : '/gouvernement/mes-zones'" />
                    <DashCard title="Projets dans mon pays" icon="🚀"
                        text="Découvrez les projets publiés sur votre territoire."
                        cta="Explorer" to="/projets" />
                    <DashCard title="Portail Gouvernement" icon="📢"
                        text="Vue publique de tous les appels à projets ouverts."
                        cta="Voir" to="/gouvernement" />
                    <DashCard title="Mon profil institution" icon="⚙️"
                        text="Complétez votre profil pour valider votre badge officiel."
                        cta="Éditer" to="/profil/government" />
                    <DashCard title="Mentorat" icon="🎓"
                        text="Partenariats avec des mentors pour accompagner vos lauréats."
                        cta="Explorer" to="/mentorat" />
                </div>

                <!-- Recent calls -->
                <div v-if="roleData.recent_calls?.length">
                    <h2 class="text-xl font-bold mb-4">Mes derniers appels</h2>
                    <div class="space-y-3">
                        <router-link v-for="c in roleData.recent_calls" :key="c.id"
                            :to="{ name: 'gouvernement.call', params: { slug: c.slug } }"
                            class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-4 hover:border-sky-200 transition group">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="govStatusClass(c.status)">{{ govStatusLabel(c.status) }}</span>
                                <span class="font-semibold group-hover:text-sky-700">{{ c.title }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-slate-500">
                                <span v-if="c.sector" class="text-xs">{{ c.sector }}</span>
                                <span v-if="c.budget">{{ fmtMoney(c.budget) }} {{ c.currency }}</span>
                                <span>{{ c.applications_count || 0 }} candidatures</span>
                            </div>
                        </router-link>
                    </div>
                </div>
            </template>

            <!-- ============================================ -->
            <!-- ADMIN-SPECIFIC -->
            <!-- ============================================ -->
            <template v-if="auth.activeRole === 'admin'">
                <!-- Alert banner for pending moderation -->
                <div v-if="pendingProjectsCount > 0"
                    class="bg-rose-50 border border-rose-200 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="text-3xl">🛡️</div>
                    <div class="flex-1">
                        <div class="font-semibold text-rose-900">
                            {{ pendingProjectsCount }} projet{{ pendingProjectsCount > 1 ? 's' : '' }} en attente de modération
                        </div>
                        <p class="text-xs text-rose-700 mt-1">Des projets nécessitent votre validation avant publication.</p>
                    </div>
                    <router-link to="/admin/moderation"
                        class="px-4 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shrink-0">
                        Modérer maintenant
                    </router-link>
                </div>

                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="text-xl font-bold">Administration de la plateforme</h2>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                    <DashCard title="Gestion utilisateurs" icon="👥"
                        text="Gérer les comptes, rôles et niveaux KYC de tous les utilisateurs."
                        cta="Ouvrir" to="/admin/utilisateurs" />
                    <DashCard title="Modération" icon="🛡️"
                        text="Approuver, rejeter ou dé-publier les projets soumis."
                        cta="Modérer" to="/admin/moderation"
                        :alert="pendingProjectsCount > 0" />
                    <DashCard title="Analytics & Reporting" icon="📈"
                        text="Vue globale : utilisateurs, levées de fonds, tendances, KYC."
                        cta="Explorer" to="/admin/analytics" />
                    <DashCard title="Mon profil admin" icon="⚙️"
                        text="Configurez votre département, responsabilités et préférences."
                        cta="Éditer" to="/profil/admin" />
                    <DashCard title="Explorer les projets" icon="🚀"
                        text="Parcourir tous les projets publiés sur la plateforme."
                        cta="Voir" to="/projets" />
                    <DashCard title="Mentorat" icon="🎓"
                        text="Consulter l'annuaire des mentors et les statistiques."
                        cta="Voir" to="/mentorat" />
                </div>
            </template>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import DashCard from '../components/DashCard.vue';
import KycBanner from '../components/KycBanner.vue';

const auth = useAuthStore();

const loading = ref(true);
const common = ref({});
const roleData = ref({});
const kpis = ref([]);

const LABELS = {
    entrepreneur: 'Entrepreneur', investor: 'Investisseur', mentor: 'Mentor',
    jobseeker: "Chercheur d'emploi", government: 'Gouvernement', admin: 'Administrateur',
};
const COLORS = {
    entrepreneur: { bg: '#ecfdf5', fg: '#16a34a' },
    investor:     { bg: '#eff6ff', fg: '#2563eb' },
    mentor:       { bg: '#f5f3ff', fg: '#7c3aed' },
    jobseeker:    { bg: '#fffbeb', fg: '#d97706' },
    government:   { bg: '#f0f9ff', fg: '#0ea5e9' },
    admin:        { bg: '#fef2f2', fg: '#ef4444' },
};

const activeRoleLabel = computed(() => LABELS[auth.activeRole] || 'Non défini');
const activeBg = computed(() => COLORS[auth.activeRole]?.bg || '#f1f5f9');
const activeFg = computed(() => COLORS[auth.activeRole]?.fg || '#475569');
const currentCompletion = computed(() => auth.completionFor(auth.activeRole));

const kycLabel = computed(() =>
    ({ none: 'Aucun', basic: 'Basique', verified: 'Vérifié', certified: 'Certifié' })[common.value?.kyc_level || auth.user?.kyc_level] || '—'
);

const planLabel = computed(() =>
    ({ free: 'Gratuit', starter: 'Starter', pro: 'Pro', enterprise: 'Enterprise' })[auth.planSlug] || auth.planSlug
);

const pendingProjectsCount = computed(() => {
    // Find pending KPI in admin KPIs
    const pendingKpi = kpis.value.find(k => k.label === 'En attente modération');
    return pendingKpi?.value || 0;
});

function fmtMoney(amount) {
    const n = parseFloat(amount) || 0;
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M €';
    if (n >= 1_000) return Math.round(n / 1_000) + 'k €';
    return n + ' €';
}

function projectStatusClass(s) {
    return {
        published: 'bg-emerald-100 text-emerald-700',
        draft: 'bg-slate-100 text-slate-600',
        pending: 'bg-amber-100 text-amber-700',
        rejected: 'bg-red-100 text-red-700',
    }[s] || 'bg-slate-100 text-slate-600';
}
function projectStatusLabel(s) {
    return { published: 'Publié', draft: 'Brouillon', pending: 'En attente', rejected: 'Rejeté' }[s] || s;
}

function invStatusClass(s) {
    return {
        pending: 'bg-amber-100 text-amber-700',
        escrow: 'bg-blue-100 text-blue-700',
        released: 'bg-emerald-100 text-emerald-700',
        refunded: 'bg-slate-100 text-slate-600',
    }[s] || 'bg-slate-100 text-slate-600';
}
function invStatusLabel(s) {
    return { pending: 'En attente', escrow: 'En escrow', released: 'Libéré', refunded: 'Remboursé' }[s] || s;
}

function govStatusClass(s) {
    return { draft: 'bg-amber-100 text-amber-700', open: 'bg-emerald-100 text-emerald-700', closed: 'bg-slate-100 text-slate-600', awarded: 'bg-violet-100 text-violet-700' }[s] || 'bg-slate-100 text-slate-600';
}
function govStatusLabel(s) {
    return { draft: 'Brouillon', open: 'Ouvert', closed: 'Clôturé', awarded: 'Attribué' }[s] || s;
}

function appStatusClass(s) {
    return {
        submitted: 'bg-amber-100 text-amber-700',
        under_review: 'bg-blue-100 text-blue-700',
        shortlisted: 'bg-violet-100 text-violet-700',
        accepted: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    }[s] || 'bg-slate-100 text-slate-600';
}
function appStatusLabel(s) {
    return { submitted: 'En attente', under_review: 'En revue', shortlisted: 'Présélectionnée', accepted: 'Acceptée', rejected: 'Rejetée' }[s] || s;
}
function levelColor(l) {
    return {
        beginner: 'bg-slate-100 text-slate-700',
        intermediate: 'bg-blue-100 text-blue-700',
        advanced: 'bg-violet-100 text-violet-700',
        expert: 'bg-amber-100 text-amber-800',
    }[l] || 'bg-slate-100 text-slate-600';
}
function levelLabel(l) {
    return { beginner: 'Débutant', intermediate: 'Inter.', advanced: 'Avancé', expert: 'Expert' }[l] || '';
}

async function loadDashboard() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/dashboard');
        common.value = data.common || {};
        roleData.value = data.role_data || {};
        kpis.value = data.role_data?.kpis || [];
    } catch (e) {
        console.error('Dashboard load error:', e);
    } finally {
        loading.value = false;
    }
}

async function switchRole(slug) {
    await auth.switchRole(slug);
    await loadDashboard();
}

onMounted(loadDashboard);

// Reload when active role changes (via role switcher in profile pages etc.)
watch(() => auth.activeRole, (newVal, oldVal) => {
    if (newVal && oldVal && newVal !== oldVal) {
        loadDashboard();
    }
});
</script>
