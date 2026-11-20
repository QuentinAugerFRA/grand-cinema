// Parcours du spectateur : films, séances, plan de salle, paiement, billet

const contenu = document.querySelector('#contenu');
const MAXIMUM_PLACES = 10;
const JOURS_PAR_PAGE = 7;
const ICONES_SALLES = { 'Premium / IMAX': 'star', 'VIP': 'workspace_premium', 'Événementielle / modulable': 'celebration' };
let rafraichissement = null;

// Affiche une vue, avec un bouton de retour vers la vue précédente
function changerVue(html, retour)
{
    clearInterval(rafraichissement);
    const boutonRetour = retour ? `<button id="boutonRetour" class="secondaire">${icone('arrow_back')}Retour</button>` : '';
    contenu.innerHTML = boutonRetour + html;
    if (retour)
    {
        contenu.querySelector('#boutonRetour').onclick = retour;
    }
}

// Les salles spéciales portent un badge, les salles standard n'en ont pas
function badgeSalle(typeSalle)
{
    return ICONES_SALLES[typeSalle] ? `<span class="badge">${icone(ICONES_SALLES[typeSalle])}${echapper(typeSalle)}</span>` : '';
}

function formaterJour(date)
{
    return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}

function formaterHeure(dateSql)
{
    return dateSql.substring(11, 16);
}

async function afficherFilms()
{
    const films = await appelerApi('GET', 'films');
    changerVue(`<h2>${icone('local_movies')}À l'affiche</h2><div class="grille">` + films.map((film) => `
        <article class="carte">
            <img src="${echapper(film.affiche)}" alt="">
            <h3>${echapper(film.titre)}</h3>
            <p>${echapper(film.genre)} · ${icone('schedule')}${film.duree_minutes} min</p>
        </article>`).join('') + '</div>');
    contenu.querySelectorAll('.carte').forEach((carte, position) => carte.onclick = () => afficherSeances(films[position]));
}

// Les séances sont affichées par semaine, regroupées par jour
async function afficherSeances(film, premierJour = 0)
{
    const seances = await appelerApi('GET', `films/${film.id}/seances`);
    const jours = [...new Set(seances.map((seance) => seance.debut.substring(0, 10)))];
    const joursAffiches = jours.slice(premierJour, premierJour + JOURS_PAR_PAGE);
    const estComplete = (seance) => seance.places_occupees >= seance.capacite;

    changerVue(`<h2>${echapper(film.titre)}</h2>` + joursAffiches.map((jour) => `
        <h3 class="jour">${formaterJour(new Date(jour + 'T12:00'))}</h3>
        <ul class="liste">` + seances.filter((seance) => seance.debut.startsWith(jour)).map((seance) => `
            <li class="${estComplete(seance) ? 'complet' : ''}" data-seance="${seance.id}">
                <strong>${icone('schedule')}${formaterHeure(seance.debut)}</strong>
                <span>${echapper(seance.salle)}</span>
                ${badgeSalle(seance.type_salle)}
                <span class="fin">${estComplete(seance) ? 'Complet' : formaterPrix(seance.tarif)}</span>
            </li>`).join('') + '</ul>').join('') + `
        <div class="pagination">
            <button id="boutonPrecedent" class="secondaire" ${premierJour === 0 ? 'hidden' : ''}>${icone('chevron_left')}Précédent</button>
            <button id="boutonSuivant" class="secondaire" ${premierJour + JOURS_PAR_PAGE >= jours.length ? 'hidden' : ''}>Suivant${icone('chevron_right')}</button>
        </div>`, afficherFilms);

    contenu.querySelectorAll('li:not(.complet)').forEach((ligne) =>
    {
        const seance = seances.find((seance) => seance.id === Number(ligne.dataset.seance));
        ligne.onclick = () => afficherPlan(film, seance, premierJour);
    });
    contenu.querySelector('#boutonPrecedent').onclick = () => afficherSeances(film, premierJour - JOURS_PAR_PAGE);
    contenu.querySelector('#boutonSuivant').onclick = () => afficherSeances(film, premierJour + JOURS_PAR_PAGE);
}

async function afficherPlan(film, seance, premierJour)
{
    let places = [];
    let placesChoisies = [];
    const retour = () => afficherSeances(film, premierJour);
    changerVue(`
        <h2>${echapper(film.titre)}</h2>
        <p>${icone('event')}${formaterDate(seance.debut)} · ${icone('meeting_room')}${echapper(seance.salle)} ${badgeSalle(seance.type_salle)}</p>
        <p class="ecran">Écran</p>
        <div id="plan"></div>
        <p class="legende">
            <span class="place"></span>Libre
            <span class="place choisie"></span>Votre sélection
            <span class="place occupee"></span>Occupée
        </p>
        <p id="total"></p>
        <button id="boutonReserver">${icone('shopping_cart')}Réserver</button>`, retour);
    const plan = contenu.querySelector('#plan');
    const boutonReserver = contenu.querySelector('#boutonReserver');

    // Une colonne vide entre deux blocs de fauteuils représente une allée
    const dessinerPlan = () =>
    {
        const placesParRangee = places.filter((place) => place.rangee === 'A').length;
        const placesParBloc = Math.ceil(placesParRangee / seance.nombre_blocs);
        plan.style.gridTemplateColumns = `repeat(${placesParRangee + seance.nombre_blocs - 1}, 1fr)`;
        plan.innerHTML = places.map((place) =>
        {
            const ligne = place.rangee.charCodeAt(0) - 64;
            const colonne = place.numero + Math.floor((place.numero - 1) / placesParBloc);
            const etat = place.occupee ? 'occupee' : (placesChoisies.includes(place.id) ? 'choisie' : '');
            return `<button class="place ${etat}" style="grid-area: ${ligne} / ${colonne}" data-place="${place.id}"
                            title="${place.rangee}${place.numero}" ${place.occupee ? 'disabled' : ''}></button>`;
        }).join('');
        contenu.querySelector('#total').innerHTML = `${icone('event_seat')}${placesChoisies.length} place(s) – ${formaterPrix(placesChoisies.length * seance.tarif)}`;
        boutonReserver.disabled = placesChoisies.length === 0;
    };

    const actualiserPlan = async () =>
    {
        places = await appelerApi('GET', `seances/${seance.id}/places`);
        const placesOccupees = places.filter((place) => place.occupee).map((place) => place.id);
        placesChoisies = placesChoisies.filter((placeId) => !placesOccupees.includes(placeId));
        dessinerPlan();
    };

    plan.onclick = (evenement) =>
    {
        const placeId = Number(evenement.target.dataset.place);
        if (placesChoisies.includes(placeId))
        {
            placesChoisies = placesChoisies.filter((placeChoisie) => placeChoisie !== placeId);
        }
        else if (placeId && placesChoisies.length < MAXIMUM_PLACES)
        {
            placesChoisies.push(placeId);
        }
        dessinerPlan();
    };

    boutonReserver.onclick = async () =>
    {
        if (!compte)
        {
            return afficherConnexion();
        }
        const reservation = await appelerApi('POST', 'reservations', { seanceId: seance.id, placeIds: placesChoisies });
        afficherPaiement(reservation, () => afficherPlan(film, seance, premierJour));
    };

    await actualiserPlan();
    rafraichissement = setInterval(actualiserPlan, 5000);
}

function afficherPaiement(reservation, retour)
{
    changerVue(`
        <h2>${icone('credit_card')}Paiement – ${formaterPrix(reservation.montant)}</h2>
        <p>${icone('timer')}Vos places sont bloquées pendant 10 minutes. Paiement simulé : aucune carte n'est débitée.</p>
        <form id="formulairePaiement">
            <input name="numeroCarte" inputmode="numeric" pattern="[0-9]{16}" placeholder="Numéro de carte (16 chiffres)" required>
            <button>${icone('lock')}Payer</button>
        </form>`, retour);
    contenu.querySelector('#formulairePaiement').onsubmit = async (evenement) =>
    {
        evenement.preventDefault();
        try
        {
            afficherBillet(await appelerApi('POST', `reservations/${reservation.id}/paiement`, saisie(evenement.target)));
        }
        catch (erreur)
        {
            afficherMessage(erreur.message);
            retour();
        }
    };
}

function afficherBillet(billet)
{
    changerVue(`
        <div class="billet">
            <h2>${echapper(billet.titre)}</h2>
            <p>${icone('event')}${formaterDate(billet.debut)}</p>
            <p>${icone('meeting_room')}${echapper(billet.salle)}</p>
            <p>${icone('event_seat')}Places : <strong>${billet.places}</strong></p>
            <div id="qrCode"></div>
            <code>${billet.code}</code>
            <p>${icone('mail')}En production, ce billet sera aussi envoyé à ${echapper(billet.email)}.</p>
        </div>`, afficherFilms);
    new QRCode(contenu.querySelector('#qrCode'), billet.code);
}

async function demarrer()
{
    await chargerCompte();
    const codeBillet = new URLSearchParams(location.search).get('billet');
    codeBillet ? afficherBillet(await appelerApi('GET', `billets/${codeBillet}`)) : afficherFilms();
}

// Un administrateur qui se connecte est envoyé vers le back-office
document.addEventListener('connexion', () =>
{
    if (compte.administrateur)
    {
        location.href = 'admin.html';
    }
});

demarrer();
