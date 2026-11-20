// Administration : films, séances, réservations, contrôle des billets, statistiques

const fenetreFilm = document.querySelector('#fenetreFilm');
const fenetreSeance = document.querySelector('#fenetreSeance');
const formulaireFilm = document.querySelector('#formulaireFilm');
const formulaireSeance = document.querySelector('#formulaireSeance');
const formulaireRecherche = document.querySelector('#formulaireRecherche');
const formulaireControle = document.querySelector('#formulaireControle');
const formulairePeriode = document.querySelector('#formulairePeriode');
let seancesDuJour = [];
let filmModifie = null;

function remplirTable(identifiant, entetes, lignes)
{
    const cellules = (valeurs, balise) => '<tr>' + valeurs.map((valeur) => `<${balise}>${valeur}</${balise}>`).join('') + '</tr>';
    document.querySelector(identifiant).innerHTML = cellules(entetes, 'th') + lignes.map((ligne) => cellules(ligne, 'td')).join('');
}

function remplirListe(liste, elements, libelle)
{
    liste.innerHTML = elements.map((element) => `<option value="${element.id}">${echapper(libelle(element))}</option>`).join('');
}

function boutonIcone(nomIcone, action, identifiant)
{
    return `<button class="discret" data-action="${action}" data-identifiant="${identifiant}">${icone(nomIcone)}</button>`;
}

// Ouvre la fenêtre de confirmation et renvoie vrai si l'utilisateur confirme
function confirmer(question)
{
    const fenetre = document.querySelector('#fenetreConfirmation');
    document.querySelector('#questionConfirmation').textContent = question;
    fenetre.returnValue = 'non';
    fenetre.showModal();
    return new Promise((repondre) => fenetre.onclose = () => repondre(fenetre.returnValue === 'oui'));
}

// Chaque onglet a sa propre adresse : /admin/films, /admin/seances...
function afficherOnglet(onglet = location.pathname.split('/')[2] || 'films')
{
    document.querySelectorAll('nav button').forEach((bouton) => bouton.classList.toggle('actif', bouton.dataset.onglet === onglet));
    document.querySelectorAll('section').forEach((section) => section.classList.toggle('actif', section.id === onglet));
}

async function afficherFilms()
{
    const films = await appelerApi('GET', 'admin/films');
    remplirTable('#tableFilms', ['Affiche', 'Titre', 'Durée', 'Genre', ''], films.map((film) => [
        `<img class="vignette" src="${echapper(film.affiche)}" alt="">`, echapper(film.titre), film.duree_minutes + ' min', echapper(film.genre),
        boutonIcone('edit', 'modifier', film.id) + boutonIcone('delete', 'supprimer', film.id)
    ]));
    remplirListe(formulaireSeance.filmId, films, (film) => film.titre);

    document.querySelector('#tableFilms').onclick = async (evenement) =>
    {
        const bouton = evenement.target.closest('button');
        const film = films.find((film) => film.id === Number(bouton?.dataset.identifiant));
        if (bouton?.dataset.action === 'supprimer' && await confirmer(`Supprimer le film « ${film.titre} » et ses séances ?`))
        {
            await appelerApi('DELETE', `admin/films/${film.id}`);
            afficherFilms();
        }
        if (bouton?.dataset.action === 'modifier')
        {
            ouvrirFilm(film);
        }
    };
}

// Sans film en paramètre, la fenêtre sert à l'ajout
function ouvrirFilm(film)
{
    filmModifie = film?.id ?? null;
    document.querySelector('#titreFilm').textContent = film ? 'Modifier le film' : 'Ajouter un film';
    formulaireFilm.titre.value = film?.titre ?? '';
    formulaireFilm.dureeMinutes.value = film?.duree_minutes ?? '';
    formulaireFilm.genre.value = film?.genre ?? '';
    formulaireFilm.fichierAffiche.value = '';
    formulaireFilm.fichierAffiche.required = !film;
    formulaireFilm.dataset.affiche = film?.affiche ?? '';
    fenetreFilm.showModal();
}

// L'affiche est envoyée à part : l'API renvoie son adresse, enregistrée ensuite avec le film
async function envoyerAffiche(fichier)
{
    const donnees = new FormData();
    donnees.append('affiche', fichier);
    const reponse = await fetch('api/admin/affiches', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: donnees });
    const contenu = await reponse.json();
    if (!reponse.ok)
    {
        throw new Error(contenu.erreur);
    }
    return contenu.affiche;
}

async function afficherSeances()
{
    const seances = await appelerApi('GET', 'admin/statistiques/remplissage');
    remplirTable('#tableSeances', ['Film', 'Début', 'Salle', 'Places vendues'], seances.map((seance) => [
        echapper(seance.titre), formaterDate(seance.debut), echapper(seance.salle), `${seance.places_vendues} / ${seance.capacite}`
    ]));
}

// La date choisie limite la liste des séances à celles du jour
async function remplirSeancesDuJour(formulaire)
{
    const jour = formulaire.jour.value;
    const seances = await appelerApi('GET', `admin/statistiques/remplissage?debut=${jour}&fin=${jour}`);
    remplirListe(formulaire.seanceId, seances, (seance) => `${seance.debut.substring(11, 16)} – ${seance.titre} – ${seance.salle}`);
    return seances;
}

async function afficherReservations()
{
    const { seanceId, recherche } = saisie(formulaireRecherche);
    const seance = seancesDuJour.find((seance) => seance.id === Number(seanceId));
    if (!seance)
    {
        document.querySelector('#ventes').textContent = 'Aucune séance ce jour-là.';
        return remplirTable('#tableReservations', [], []);
    }
    const reservations = await appelerApi('GET', 'admin/reservations?' + new URLSearchParams({ seanceId, recherche }));
    const placesVendues = reservations.filter((reservation) => reservation.statut === 'payee')
        .reduce((total, reservation) => total + reservation.nombre_places, 0);
    document.querySelector('#ventes').innerHTML = `${icone('confirmation_number')}${placesVendues} places vendues sur ${seance.capacite}`;
    remplirTable('#tableReservations', ['N°', 'E-mail', 'Statut', 'Places', 'Montant', ''], reservations.map((reservation) => [
        reservation.id, echapper(reservation.email), reservation.statut, reservation.nombre_places, formaterPrix(reservation.montant),
        reservation.statut === 'annulee' ? '' : boutonIcone('cancel', 'annuler', reservation.id)
    ]));
}

async function afficherStatistiques()
{
    const periode = new URLSearchParams(saisie(formulairePeriode));
    const parSalle = formulairePeriode.regroupement.value === 'salle';
    const remplissage = await appelerApi('GET', 'admin/statistiques/remplissage?' + periode);
    const affluence = await appelerApi('GET', 'admin/statistiques/affluence?' + periode);
    const taux = (ligne) => ligne.taux > 90 ? `<span class="alerte">${icone('warning')}${ligne.taux} %</span>` : `${ligne.taux} %`;

    remplirTable('#tableRemplissage', parSalle ? ['Salle', 'Capacité', 'Places vendues', 'Taux'] : ['Film', 'Début', 'Salle', 'Capacité', 'Places vendues', 'Taux'],
        remplissage.map((ligne) => [
            ...(parSalle ? [] : [echapper(ligne.titre), formaterDate(ligne.debut)]),
            echapper(ligne.salle), ligne.capacite, ligne.places_vendues, taux(ligne)
        ]));
    remplirTable('#tableAffluence', ['Jour', 'Heure', 'Places réservées', 'Année précédente'], affluence.map((creneau) => [
        new Date(creneau.jour).toLocaleDateString('fr-FR'), creneau.heure + ' h', creneau.places_reservees, creneau.annee_precedente
    ]));
    document.querySelector('#lienCsv').href = `api/admin/statistiques/remplissage?${periode}&format=csv`;
}

async function demarrer()
{
    await chargerCompte();
    if (!compte?.administrateur)
    {
        return afficherConnexion();
    }
    const salles = await appelerApi('GET', 'admin/salles');
    remplirListe(formulaireSeance.salleId, salles, (salle) => `${salle.nom} – ${salle.type} (${salle.capacite} places)`);
    formulairePeriode.debut.value = dateLocale();
    formulairePeriode.fin.value = dateLocale(30);
    formulaireRecherche.jour.value = dateLocale();
    formulaireControle.jour.value = dateLocale();
    afficherOnglet();
    afficherFilms();
    afficherSeances();
    afficherStatistiques();
    remplirSeancesDuJour(formulaireControle);
    seancesDuJour = await remplirSeancesDuJour(formulaireRecherche);
    afficherReservations();
}

document.querySelector('#boutonAjouterFilm').onclick = () => ouvrirFilm();
document.querySelector('#boutonPlanifierSeance').onclick = () => fenetreSeance.showModal();

document.querySelector('nav').onclick = (evenement) =>
{
    history.pushState(null, '', `/admin/${evenement.target.closest('button').dataset.onglet}`);
    afficherOnglet();
};
window.onpopstate = () => afficherOnglet();

formulaireFilm.onsubmit = async (evenement) =>
{
    evenement.preventDefault();
    const { fichierAffiche, ...film } = saisie(formulaireFilm);
    film.affiche = fichierAffiche.size ? await envoyerAffiche(fichierAffiche) : formulaireFilm.dataset.affiche;
    await appelerApi(filmModifie ? 'PUT' : 'POST', 'admin/films' + (filmModifie ? `/${filmModifie}` : ''), film);
    fenetreFilm.close();
    afficherMessage('Film enregistré.');
    afficherFilms();
};

formulaireSeance.onsubmit = async (evenement) =>
{
    evenement.preventDefault();
    await appelerApi('POST', 'admin/seances', saisie(formulaireSeance));
    fenetreSeance.close();
    afficherMessage('Séance planifiée.');
    afficherSeances();
};

formulaireRecherche.oninput = async (evenement) =>
{
    if (evenement.target.name === 'jour')
    {
        seancesDuJour = await remplirSeancesDuJour(formulaireRecherche);
    }
    afficherReservations();
};
formulaireRecherche.onsubmit = (evenement) => evenement.preventDefault();

document.querySelector('#tableReservations').onclick = async (evenement) =>
{
    const bouton = evenement.target.closest('button');
    if (bouton && await confirmer(`Annuler et rembourser la réservation n° ${bouton.dataset.identifiant} ?`))
    {
        await appelerApi('POST', `admin/reservations/${bouton.dataset.identifiant}/annulation`);
        afficherMessage('Réservation annulée.');
        afficherReservations();
    }
};

formulaireControle.onsubmit = async (evenement) =>
{
    evenement.preventDefault();
    const controle = saisie(formulaireControle);
    const resultat = document.querySelector('#resultatControle');
    try
    {
        const billet = await appelerApi('POST', `admin/billets/${controle.code.trim()}/validation`, { seanceId: Number(controle.seanceId) });
        resultat.innerHTML = `<strong class="valide">${icone('check_circle')}Billet valide</strong> – places ${billet.places} – ${echapper(billet.email)}`;
        formulaireControle.code.value = '';
    }
    catch (erreur)
    {
        resultat.innerHTML = `<strong class="alerte">${icone('cancel')}Billet refusé</strong> – ${echapper(erreur.message)}`;
    }
};

formulaireControle.jour.oninput = () => remplirSeancesDuJour(formulaireControle);

formulairePeriode.onsubmit = (evenement) =>
{
    evenement.preventDefault();
    afficherStatistiques();
};

document.addEventListener('connexion', demarrer);
setInterval(afficherReservations, 5000);
demarrer();
