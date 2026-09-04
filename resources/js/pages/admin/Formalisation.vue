<template>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-6 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Hub de formalisation</h1>
                <p class="text-slate-600 dark:text-slate-300 mt-1">
                    Parcours de formalisation par pays, modèles de business plan et partenaires de financement.
                </p>
            </div>
            <router-link to="/dashboard"
                class="text-sm font-semibold text-red-600 dark:text-red-400 hover:underline">
                ← Dashboard admin
            </router-link>
        </div>

        <!-- Compteurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div v-for="s in statCards" :key="s.label"
                class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4">
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ s.value }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ s.label }}</div>
            </div>
        </div>

        <!-- Onglets -->
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700 mb-6 overflow-x-auto">
            <button v-for="t in TABS" :key="t.id" @click="switchTab(t.id)"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px whitespace-nowrap transition"
                :class="tab === t.id
                    ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'">
                {{ t.icon }} {{ t.label }}
            </button>
        </div>

        <!-- Filtres + création -->
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 mb-6">
            <div class="grid md:grid-cols-4 gap-3">
                <input v-model="filters.q" @input="debouncedLoad" type="search"
                    :id="`f-search-${tab}`" :name="`f-search-${tab}`"
                    :placeholder="tab === 'templates' ? 'Rechercher un modèle…' : 'Rechercher (titre, pays, institution)…'"
                    class="md:col-span-2 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm" />

                <select v-if="tab !== 'templates'" v-model="filters.country" @change="load"
                    :id="`f-country-${tab}`" :name="`f-country-${tab}`"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous les pays</option>
                    <option v-for="c in countryOptions" :key="c" :value="c">{{ c }}</option>
                </select>

                <select v-else v-model="filters.sector" @change="load"
                    id="f-sector" name="f-sector"
                    class="px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm">
                    <option value="">Tous les secteurs</option>
                    <option v-for="s in overview.sectors || []" :key="s" :value="s">{{ s }}</option>
                </select>

                <button @click="openCreate"
                    class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                    + {{ currentTab.createLabel }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-slate-500 dark:text-slate-400 py-8">Chargement…</div>
        <div v-else-if="!items.length" class="text-slate-500 dark:text-slate-400 py-8 text-center">
            Aucun élément. Cliquez sur « {{ currentTab.createLabel }} » pour en créer un.
        </div>

        <!-- ═══════════ Onglet 1 : parcours par pays ═══════════ -->
        <div v-else-if="tab === 'steps'"
            class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 w-16">#</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Étape</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Institution</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Délai / Coût</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-for="s in items" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-black">
                                {{ s.order }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ s.title }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">{{ s.description }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ s.country }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ s.institution || '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>{{ s.estimated_duration || '—' }}</div>
                            <div>{{ s.estimated_cost || '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="move(s, -1)" :disabled="!canMove(s, -1)"
                                class="px-2 py-1 text-slate-500 hover:text-emerald-600 disabled:opacity-30" title="Monter">↑</button>
                            <button @click="move(s, 1)" :disabled="!canMove(s, 1)"
                                class="px-2 py-1 text-slate-500 hover:text-emerald-600 disabled:opacity-30" title="Descendre">↓</button>
                            <button @click="openEdit(s)" class="px-2 py-1 text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Éditer</button>
                            <button @click="remove(s)" :disabled="deletingId === s.id"
                                class="px-2 py-1 text-rose-600 dark:text-rose-400 font-semibold hover:underline disabled:opacity-50">
                                {{ deletingId === s.id ? '…' : 'Suppr.' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ═══════════ Onglet 2 : modèles de business plan ═══════════ -->
        <div v-else-if="tab === 'templates'"
            class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Modèle</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Secteur</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Sections</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Accès</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Télécharg.</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-for="t in items" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ t.title }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">{{ t.description }}</div>
                        </td>
                        <td class="px-4 py-3">{{ t.sector }}</td>
                        <td class="px-4 py-3">{{ (t.sections || []).length }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="t.is_free
                                    ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300'">
                                {{ t.is_free ? 'Gratuit' : 'Payant' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ t.downloads_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="openEdit(t)" class="px-2 py-1 text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Éditer</button>
                            <button @click="remove(t)" :disabled="deletingId === t.id"
                                class="px-2 py-1 text-rose-600 dark:text-rose-400 font-semibold hover:underline disabled:opacity-50">
                                {{ deletingId === t.id ? '…' : 'Suppr.' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ═══════════ Onglet 3 : partenaires ═══════════ -->
        <div v-else class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Partenaire</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Pays</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Montants</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-for="p in items" :key="p.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ p.name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">{{ p.description }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ p.country }}</td>
                        <td class="px-4 py-3">{{ p.type }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ p.min_amount || '—' }} → {{ p.max_amount || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="p.is_active
                                    ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                                {{ p.is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="openEdit(p)" class="px-2 py-1 text-emerald-700 dark:text-emerald-400 font-semibold hover:underline">Éditer</button>
                            <button @click="remove(p)" :disabled="deletingId === p.id"
                                class="px-2 py-1 text-rose-600 dark:text-rose-400 font-semibold hover:underline disabled:opacity-50">
                                {{ deletingId === p.id ? '…' : 'Suppr.' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-6">
            <button v-for="n in meta.last_page" :key="n" @click="goToPage(n)"
                class="px-3 py-1.5 rounded-md text-sm font-semibold border"
                :class="n === meta.current_page
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'">
                {{ n }}
            </button>
        </div>

        <!-- ═══════════ Modale d'édition ═══════════ -->
        <Modal v-model="showModal" size="lg"
            :title="(form.id ? 'Modifier — ' : 'Nouveau — ') + currentTab.label">
            <form id="formalisation-form" @submit.prevent="save" class="space-y-5">

                <!-- Étape de formalisation -->
                <template v-if="tab === 'steps'">
                    <div class="grid md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label for="st-title" class="block text-xs font-medium mb-1">Titre de l'étape *</label>
                            <input id="st-title" name="title" v-model="form.title" type="text" maxlength="200" required
                                placeholder="Immatriculation au RCCM" class="fld" />
                        </div>
                        <div>
                            <label for="st-order" class="block text-xs font-medium mb-1">Position
                                <span class="text-slate-400">(vide = en fin de parcours)</span>
                            </label>
                            <input id="st-order" name="order" v-model.number="form.order" type="number" min="1" max="255" class="fld" />
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label for="st-country" class="block text-xs font-medium mb-1">Pays *</label>
                            <input id="st-country" name="country" v-model="form.country" type="text" maxlength="80" required
                                list="country-list" placeholder="Gabon" class="fld" />
                            <datalist id="country-list">
                                <option v-for="c in countryOptions" :key="c" :value="c" />
                            </datalist>
                        </div>
                        <div>
                            <label for="st-institution" class="block text-xs font-medium mb-1">Institution</label>
                            <input id="st-institution" name="institution" v-model="form.institution" type="text" maxlength="200"
                                placeholder="ANPI-Gabon — Guichet Unique" class="fld" />
                        </div>
                    </div>
                    <div>
                        <label for="st-description" class="block text-xs font-medium mb-1">Description *</label>
                        <textarea id="st-description" name="description" v-model="form.description" rows="4" maxlength="5000" required
                            class="fld"></textarea>
                    </div>
                    <div class="grid md:grid-cols-3 gap-3">
                        <div>
                            <label for="st-duration" class="block text-xs font-medium mb-1">Délai estimé</label>
                            <input id="st-duration" name="estimated_duration" v-model="form.estimated_duration" type="text" maxlength="100"
                                placeholder="2-5 jours ouvrés" class="fld" />
                        </div>
                        <div>
                            <label for="st-cost" class="block text-xs font-medium mb-1">Coût estimé</label>
                            <input id="st-cost" name="estimated_cost" v-model="form.estimated_cost" type="text" maxlength="100"
                                placeholder="25 000 XAF" class="fld" />
                        </div>
                        <div>
                            <label for="st-link" class="block text-xs font-medium mb-1">Lien officiel</label>
                            <input id="st-link" name="link" v-model="form.link" type="url" maxlength="300"
                                placeholder="https://…" class="fld" />
                        </div>
                    </div>
                    <div>
                        <label for="st-docs" class="block text-xs font-medium mb-1">Documents requis
                            <span class="text-slate-400">(un par ligne)</span>
                        </label>
                        <textarea id="st-docs" name="required_documents" v-model="docsInput" rows="4"
                            placeholder="Statuts signés&#10;Casier judiciaire&#10;Pièce d'identité du gérant" class="fld"></textarea>
                    </div>
                    <div>
                        <label for="st-tips" class="block text-xs font-medium mb-1">Conseils pratiques</label>
                        <textarea id="st-tips" name="tips" v-model="form.tips" rows="3" maxlength="2000" class="fld"></textarea>
                    </div>
                </template>

                <!-- Modèle de business plan -->
                <template v-else-if="tab === 'templates'">
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label for="tp-title" class="block text-xs font-medium mb-1">Titre *</label>
                            <input id="tp-title" name="title" v-model="form.title" type="text" maxlength="200" required class="fld" />
                        </div>
                        <div>
                            <label for="tp-sector" class="block text-xs font-medium mb-1">Secteur *</label>
                            <input id="tp-sector" name="sector" v-model="form.sector" type="text" maxlength="100" required
                                list="sector-list" placeholder="agriculture" class="fld" />
                            <datalist id="sector-list">
                                <option v-for="s in overview.sectors || []" :key="s" :value="s" />
                            </datalist>
                        </div>
                    </div>
                    <div>
                        <label for="tp-description" class="block text-xs font-medium mb-1">Description *</label>
                        <textarea id="tp-description" name="description" v-model="form.description" rows="3" maxlength="5000" required class="fld"></textarea>
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                            Sections du business plan *
                        </legend>
                        <div v-for="(sec, i) in form.sections" :key="i" class="grid md:grid-cols-12 gap-2 items-start">
                            <input v-model="sec.title" type="text" maxlength="200" placeholder="Résumé exécutif"
                                :id="`sec-t-${i}`" :name="`sections[${i}][title]`"
                                class="fld md:col-span-4" />
                            <input v-model="sec.prompt" type="text" maxlength="1000" placeholder="Décrivez en 10 lignes votre projet…"
                                :id="`sec-p-${i}`" :name="`sections[${i}][prompt]`"
                                class="fld md:col-span-7" />
                            <button type="button" @click="form.sections.splice(i, 1)"
                                class="md:col-span-1 px-2 py-2 text-rose-600 hover:text-rose-700 text-sm font-bold">✕</button>
                        </div>
                        <button type="button" @click="form.sections.push({ title: '', prompt: '' })"
                            class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">
                            + Ajouter une section
                        </button>
                    </fieldset>

                    <div class="grid md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label for="tp-lang" class="block text-xs font-medium mb-1">Langue</label>
                            <input id="tp-lang" name="language" v-model="form.language" type="text" maxlength="20" placeholder="fr" class="fld" />
                        </div>
                        <label class="flex items-center gap-2 text-sm pb-2">
                            <input id="tp-free" name="is_free" type="checkbox" v-model="form.is_free"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            Modèle gratuit
                        </label>
                    </div>
                </template>

                <!-- Partenaire de microfinance -->
                <template v-else>
                    <div class="grid md:grid-cols-3 gap-3">
                        <div>
                            <label for="pt-name" class="block text-xs font-medium mb-1">Nom *</label>
                            <input id="pt-name" name="name" v-model="form.name" type="text" maxlength="200" required class="fld" />
                        </div>
                        <div>
                            <label for="pt-country" class="block text-xs font-medium mb-1">Pays *</label>
                            <input id="pt-country" name="country" v-model="form.country" type="text" maxlength="80" required
                                list="country-list" class="fld" />
                        </div>
                        <div>
                            <label for="pt-type" class="block text-xs font-medium mb-1">Type *</label>
                            <input id="pt-type" name="type" v-model="form.type" type="text" maxlength="80"
                                list="type-list" required placeholder="IMF" class="fld" />
                            <datalist id="type-list">
                                <option value="IMF" /><option value="Banque" />
                                <option value="Fintech" /><option value="Coopérative" />
                            </datalist>
                        </div>
                    </div>
                    <div>
                        <label for="pt-description" class="block text-xs font-medium mb-1">Description</label>
                        <textarea id="pt-description" name="description" v-model="form.description" rows="3" maxlength="5000" class="fld"></textarea>
                    </div>
                    <div>
                        <label for="pt-products" class="block text-xs font-medium mb-1">Produits
                            <span class="text-slate-400">(séparés par des virgules)</span>
                        </label>
                        <input id="pt-products" name="products" v-model="productsInput" type="text"
                            placeholder="Micro-crédit, Épargne, Crédit PME" class="fld" />
                    </div>
                    <div class="grid md:grid-cols-3 gap-3">
                        <div>
                            <label for="pt-min" class="block text-xs font-medium mb-1">Montant min.</label>
                            <input id="pt-min" name="min_amount" v-model="form.min_amount" type="text" maxlength="50" placeholder="50 000 XAF" class="fld" />
                        </div>
                        <div>
                            <label for="pt-max" class="block text-xs font-medium mb-1">Montant max.</label>
                            <input id="pt-max" name="max_amount" v-model="form.max_amount" type="text" maxlength="50" placeholder="10 000 000 XAF" class="fld" />
                        </div>
                        <div>
                            <label for="pt-rate" class="block text-xs font-medium mb-1">Taux d'intérêt</label>
                            <input id="pt-rate" name="interest_rate" v-model="form.interest_rate" type="text" maxlength="50" placeholder="1,5% mensuel" class="fld" />
                        </div>
                    </div>
                    <div class="grid md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label for="pt-website" class="block text-xs font-medium mb-1">Site web</label>
                            <input id="pt-website" name="website" v-model="form.website" type="url" maxlength="300" placeholder="https://…" class="fld" />
                        </div>
                        <div>
                            <label for="pt-email" class="block text-xs font-medium mb-1">Email contact</label>
                            <input id="pt-email" name="contact_email" v-model="form.contact_email" type="email" maxlength="200" class="fld" />
                        </div>
                        <label class="flex items-center gap-2 text-sm pb-2">
                            <input id="pt-active" name="is_active" type="checkbox" v-model="form.is_active"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            Partenaire actif
                        </label>
                    </div>
                </template>

                <p v-if="formError" class="text-sm text-rose-600 dark:text-rose-400">{{ formError }}</p>
            </form>

            <template #footer="{ close }">
                <button type="button" @click="close"
                    class="px-5 py-2 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    Annuler
                </button>
                <button type="submit" form="formalisation-form" :disabled="saving"
                    class="px-5 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm disabled:opacity-50">
                    {{ saving ? 'Enregistrement…' : (form.id ? 'Mettre à jour' : 'Créer') }}
                </button>
            </template>
        </Modal>

        <Teleport to="body">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-50 max-w-sm px-5 py-3 rounded-lg shadow-lg text-sm font-medium"
                :class="toast.tone === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
                {{ toast.message }}
            </div>
        </Teleport>
    </section>
</template>

<script setup>
/**
 * Administration du Hub de formalisation.
 *
 * Une seule page, trois référentiels (onglets) :
 *   • steps     — parcours de formalisation par pays (+ réordonnancement)
 *   • templates — modèles de business plan
 *   • partners  — partenaires de microfinance
 *
 * Les trois partagent la même mécanique liste / modale / toast ; seul le
 * formulaire change, d'où la table `TABS` qui porte endpoint et libellés.
 */
import { computed, onMounted, reactive, ref } from 'vue';
import Modal from '../../components/Modal.vue';

const TABS = [
    { id: 'steps', label: 'Parcours pays', icon: '🧭', endpoint: 'steps', createLabel: 'Nouvelle étape' },
    { id: 'templates', label: 'Modèles de business plan', icon: '📝', endpoint: 'templates', createLabel: 'Nouveau modèle' },
    { id: 'partners', label: 'Partenaires financiers', icon: '🏦', endpoint: 'partners', createLabel: 'Nouveau partenaire' },
];

const tab = ref('steps');
const currentTab = computed(() => TABS.find(t => t.id === tab.value) || TABS[0]);
const endpoint = computed(() => `/api/admin/formalisation/${currentTab.value.endpoint}`);

const items = ref([]);
const meta = ref({});
const overview = ref({ counts: {}, countries: [], sectors: [] });
const loading = ref(true);
const page = ref(1);
const filters = reactive({ q: '', country: '', sector: '' });

const showModal = ref(false);
const saving = ref(false);
const formError = ref('');
const deletingId = ref(null);
const toast = ref(null);

const countryOptions = computed(() => (overview.value.countries || []).map(c => c.country));

const statCards = computed(() => {
    const c = overview.value.counts || {};
    return [
        { label: 'Pays couverts', value: c.countries ?? 0 },
        { label: 'Étapes de formalisation', value: c.steps ?? 0 },
        { label: 'Modèles de business plan', value: c.templates ?? 0 },
        { label: 'Partenaires financiers', value: c.partners ?? 0 },
    ];
});

// ── Formulaire ──────────────────────────────────────────────
function emptyForm() {
    if (tab.value === 'steps') {
        return {
            id: null, country: filters.country || '', order: null, title: '', description: '',
            institution: '', required_documents: [], estimated_duration: '', estimated_cost: '',
            link: '', tips: '',
        };
    }
    if (tab.value === 'templates') {
        return {
            id: null, title: '', sector: '', description: '',
            sections: [{ title: '', prompt: '' }], language: 'fr', is_free: true,
        };
    }
    return {
        id: null, name: '', country: filters.country || '', type: 'IMF', description: '',
        products: [], min_amount: '', max_amount: '', interest_rate: '',
        website: '', contact_email: '', is_active: true,
    };
}
const form = reactive(emptyForm());

const docsInput = computed({
    get: () => Array.isArray(form.required_documents) ? form.required_documents.join('\n') : '',
    set: (v) => { form.required_documents = String(v).split('\n').map(s => s.trim()).filter(Boolean); },
});
const productsInput = computed({
    get: () => Array.isArray(form.products) ? form.products.join(', ') : '',
    set: (v) => { form.products = String(v).split(',').map(s => s.trim()).filter(Boolean); },
});

// ── Chargement ──────────────────────────────────────────────
let debounce;
function debouncedLoad() {
    clearTimeout(debounce);
    debounce = setTimeout(() => { page.value = 1; load(); }, 300);
}

function switchTab(id) {
    tab.value = id;
    page.value = 1;
    filters.q = '';
    load();
}

function goToPage(n) {
    page.value = n;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function loadOverview() {
    try {
        const { data } = await window.axios.get('/api/admin/formalisation/overview');
        overview.value = data;
    } catch { /* les compteurs restent à zéro */ }
}

async function load() {
    loading.value = true;
    try {
        const params = { page: page.value, q: filters.q };
        if (tab.value === 'templates') params.sector = filters.sector;
        else params.country = filters.country;
        Object.keys(params).forEach(k => (params[k] === '' || params[k] == null) && delete params[k]);

        const { data } = await window.axios.get(endpoint.value, { params });
        items.value = data.data || [];
        meta.value = { total: data.total, last_page: data.last_page, current_page: data.current_page };
    } finally {
        loading.value = false;
    }
}

// ── CRUD ────────────────────────────────────────────────────
function openCreate() {
    Object.assign(form, emptyForm());
    formError.value = '';
    showModal.value = true;
}

async function openEdit(row) {
    formError.value = '';
    Object.assign(form, emptyForm());
    try {
        const { data } = await window.axios.get(`${endpoint.value}/${row.id}`);
        applyRow(data.data);
    } catch {
        applyRow(row);
    }
    showModal.value = true;
}

/** Normalise les champs JSON qui peuvent revenir null depuis l'API. */
function applyRow(row) {
    Object.assign(form, row);
    form.required_documents = Array.isArray(row.required_documents) ? row.required_documents : [];
    form.products = Array.isArray(row.products) ? row.products : [];
    form.sections = Array.isArray(row.sections) && row.sections.length
        ? row.sections.map(s => ({ title: s.title || '', prompt: s.prompt || '' }))
        : [{ title: '', prompt: '' }];
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form };
        delete payload.id;
        delete payload.slug;
        delete payload.created_at;
        delete payload.updated_at;
        delete payload.downloads_count;

        if (tab.value === 'templates') {
            payload.sections = (payload.sections || []).filter(s => s.title?.trim());
            if (!payload.sections.length) {
                formError.value = 'Ajoutez au moins une section au modèle.';
                return;
            }
        }

        // '' → null pour que les règles `nullable|url|email` ne rejettent pas
        // une chaîne vide laissée par un champ non renseigné.
        Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null; });

        if (form.id) {
            const { data } = await window.axios.patch(`${endpoint.value}/${form.id}`, payload);
            showToast(data.message || 'Mise à jour effectuée.');
        } else {
            const { data } = await window.axios.post(endpoint.value, payload);
            showToast(data.message || 'Élément créé.');
        }
        showModal.value = false;
        await Promise.all([load(), loadOverview()]);
    } catch (e) {
        formError.value = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})[0]?.[0]
            || 'Erreur lors de l\'enregistrement.';
    } finally {
        saving.value = false;
    }
}

async function remove(row) {
    const label = row.title || row.name;
    const extra = tab.value === 'steps'
        ? '\n\nLe suivi de progression des utilisateurs sur cette étape sera également supprimé.'
        : '';
    if (!confirm(`Supprimer « ${label} » ?${extra}`)) return;

    deletingId.value = row.id;
    try {
        const { data } = await window.axios.delete(`${endpoint.value}/${row.id}`);
        showToast(data.message || 'Élément supprimé.');
        await Promise.all([load(), loadOverview()]);
    } catch (e) {
        showToast(e?.response?.data?.message || 'Erreur lors de la suppression.', 'error');
    } finally {
        deletingId.value = null;
    }
}

// ── Réordonnancement du parcours (onglet « steps ») ──────────
/** Étapes du même pays visibles dans la liste, dans l'ordre affiché. */
function siblings(step) {
    return items.value.filter(s => s.country === step.country).sort((a, b) => a.order - b.order);
}

function canMove(step, delta) {
    const list = siblings(step);
    const i = list.findIndex(s => s.id === step.id);
    return i !== -1 && i + delta >= 0 && i + delta < list.length;
}

async function move(step, delta) {
    if (!canMove(step, delta)) return;

    const list = siblings(step);
    const i = list.findIndex(s => s.id === step.id);
    [list[i], list[i + delta]] = [list[i + delta], list[i]];

    try {
        await window.axios.post('/api/admin/formalisation/steps/reorder', {
            country: step.country,
            ids: list.map(s => s.id),
        });
        await load();
    } catch (e) {
        showToast(e?.response?.data?.message || 'Réordonnancement impossible.', 'error');
    }
}

function showToast(message, tone = 'success') {
    toast.value = { message, tone };
    setTimeout(() => { toast.value = null; }, 4000);
}

onMounted(async () => {
    await Promise.all([loadOverview(), load()]);
});
</script>

<style scoped>
/* Champ de formulaire — CSS brut plutôt que @apply : en Tailwind v4 les
   directives dans un <style> de SFC exigent un @reference au thème. Même
   rendu que le `.input` du formulaire projet. */
.fld {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    outline: none;
    background: white;
    color: rgb(15 23 42);        /* slate-900 */
    font-size: 0.875rem;
    line-height: 1.25rem;
}
.fld::placeholder { color: rgb(148 163 184); }  /* slate-400 */
.fld:focus { border-color: rgb(52 211 153); }   /* emerald-400 */

:global(.dark) .fld {
    background: rgb(15 23 42);   /* slate-900 */
    border-color: rgb(51 65 85); /* slate-700 */
    color: rgb(241 245 249);     /* slate-100 */
}
:global(.dark) .fld::placeholder { color: rgb(100 116 139); }
:global(.dark) .fld:focus { border-color: rgb(52 211 153); }
:global(.dark) .fld option { background: rgb(15 23 42); color: rgb(241 245 249); }
</style>
