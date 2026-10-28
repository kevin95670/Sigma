/**
 * Formulaire de réservation : charge les créneaux disponibles quand on change
 * la date ou le nombre de personnes, puis envoie la réservation sans recharger.
 */
(function () {
    'use strict';

    var form = document.querySelector('.resto-resa');
    if (!form || typeof restoResa === 'undefined') {
        return;
    }

    var zoneCreneaux = form.querySelector('.resto-resa-creneaux');
    var zoneMessage  = form.querySelector('.resto-resa-message');
    var champDate    = form.querySelector('[name="date"]');
    var bouton       = form.querySelector('.resto-resa-bouton');

    function personnes() {
        var choisi = form.querySelector('[name="personnes"]:checked');
        return choisi ? choisi.value : '';
    }

    function afficherMessage(texte, erreur) {
        zoneMessage.textContent = texte || '';
        zoneMessage.className = 'resto-resa-message' + (texte ? (erreur ? ' resto-resa-message--erreur' : ' resto-resa-message--ok') : '');
    }

    function note(texte) {
        zoneCreneaux.innerHTML = '';
        var p = document.createElement('p');
        p.className = 'resto-resa-note';
        p.textContent = texte;
        zoneCreneaux.appendChild(p);
    }

    // Dessine les créneaux : Midi / Soir, les complets sont barrés.
    function dessiner(services) {
        zoneCreneaux.innerHTML = '';
        Object.keys(services).forEach(function (cle) {
            var service = services[cle];
            var titre = document.createElement('span');
            titre.className = 'resto-resa-service';
            titre.textContent = service.label;
            zoneCreneaux.appendChild(titre);

            var chips = document.createElement('div');
            chips.className = 'resto-resa-chips';
            service.creneaux.forEach(function (creneau) {
                var label = document.createElement('label');
                label.className = 'resto-chip';
                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'heure';
                input.value = creneau.heure;
                input.disabled = !creneau.dispo;
                var span = document.createElement('span');
                span.textContent = creneau.heure;
                label.appendChild(input);
                label.appendChild(span);
                chips.appendChild(label);
            });
            zoneCreneaux.appendChild(chips);
        });
    }

    function chargerCreneaux() {
        afficherMessage('');
        if (!champDate.value) {
            note('Choisissez une date pour voir les horaires.');
            return;
        }
        note('Chargement…');
        var url = restoResa.ajax + '?action=resto_creneaux&date=' + encodeURIComponent(champDate.value) +
                  '&personnes=' + encodeURIComponent(personnes());
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (reponse) {
                if (reponse.success) {
                    dessiner(reponse.data.services);
                } else {
                    note(reponse.data.message);
                }
            })
            .catch(function () { note('Impossible de charger les horaires, réessayez.'); });
    }

    champDate.addEventListener('change', chargerCreneaux);
    form.querySelectorAll('[name="personnes"]').forEach(function (radio) {
        radio.addEventListener('change', chargerCreneaux);
    });

    form.addEventListener('submit', function (evenement) {
        evenement.preventDefault();
        afficherMessage('');

        if (!champDate.value) {
            afficherMessage('Choisissez une date.', true);
            return;
        }
        if (!form.querySelector('[name="heure"]:checked')) {
            afficherMessage('Choisissez un horaire.', true);
            return;
        }

        var donnees = new FormData(form);
        donnees.append('action', 'resto_reserver');
        donnees.append('nonce', restoResa.nonce);

        bouton.disabled = true;
        fetch(restoResa.ajax, { method: 'POST', body: donnees })
            .then(function (r) { return r.json(); })
            .then(function (reponse) {
                bouton.disabled = false;
                if (reponse.success) {
                    var ok = document.createElement('div');
                    ok.className = 'resto-resa-ok';
                    var h = document.createElement('p');
                    h.className = 'resto-resa-ok-titre';
                    h.textContent = reponse.data.message;
                    ok.appendChild(h);
                    if (reponse.data.detail) {
                        var d = document.createElement('p');
                        d.textContent = reponse.data.detail;
                        ok.appendChild(d);
                    }
                    form.replaceWith(ok);
                } else {
                    afficherMessage(reponse.data.message, true);
                    if (reponse.data.refresh) {
                        chargerCreneaux();
                        afficherMessage(reponse.data.message, true);
                    }
                }
            })
            .catch(function () {
                bouton.disabled = false;
                afficherMessage('Une erreur est survenue, réessayez dans un instant.', true);
            });
    });
})();