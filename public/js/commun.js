// Fonctions partagées par le site et l'administration

const fenetreConnexion = document.querySelector('#fenetreConnexion');
const formulaireConnexion = document.querySelector('#formulaireConnexion');
const boutonCompte = document.querySelector('#boutonCompte');
const boutonBascule = document.querySelector('#boutonBascule');
let compte = null;

async function appelerApi(methode, chemin, donnees)
{
    const reponse = await fetch('api/' + chemin, {
        method: methode,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: donnees ? JSON.stringify(donnees) : undefined
    });
    const contenu = reponse.status === 204 ? null : await reponse.json();
    if (!reponse.ok)
    {
        throw new Error(contenu.erreur ?? 'Identifiants incorrects. Après 5 échecs, la connexion est bloquée 15 minutes.');
    }
    return contenu;
}

function icone(nom)
{
    return `<span class="material-icons">${nom}</span>`;
}

function afficherMessage(texte)
{
    const message = document.querySelector('#message');
    message.textContent = texte;
    message.showPopover();
    setTimeout(() => message.hidePopover(), 4000);
}

function echapper(texte)
{
    return String(texte).replace(/[&<>"]/g, (caractere) => `&#${caractere.charCodeAt(0)};`);
}

function formaterDate(dateSql)
{
    const options = { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
    return new Date(dateSql.replace(' ', 'T')).toLocaleString('fr-FR', options);
}

function formaterPrix(montant)
{
    return Number(montant).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

// Date locale au format AAAA-MM-JJ, décalée d'un nombre de jours
function dateLocale(decalageJours = 0)
{
    return new Date(Date.now() + decalageJours * 24 * 3600 * 1000).toLocaleDateString('en-CA');
}

function saisie(formulaire)
{
    return Object.fromEntries(new FormData(formulaire));
}

async function chargerCompte()
{
    compte = await appelerApi('GET', 'compte');
    boutonCompte.innerHTML = compte ? icone('logout') + '<span class="libelle">Déconnexion</span>' : icone('person') + 'Connexion';
    boutonCompte.title = compte ? 'Déconnexion' : 'Connexion';
}

// La même fenêtre sert à la connexion et à la création de compte
function afficherConnexion(inscription = false)
{
    document.querySelector('#titreConnexion').textContent = inscription ? 'Créer un compte' : 'Connexion';
    document.querySelector('#boutonValider').innerHTML = inscription ? icone('person_add') + 'Créer mon compte' : icone('login') + 'Se connecter';
    document.querySelector('#informationInscription').hidden = !inscription;
    if (boutonBascule)
    {
        boutonBascule.innerHTML = inscription ? icone('login') + 'J\'ai déjà un compte' : icone('person_add') + 'Créer un compte';
    }
    fenetreConnexion.dataset.inscription = inscription ? 'oui' : '';
    if (!fenetreConnexion.open)
    {
        fenetreConnexion.showModal();
    }
}

async function connecter()
{
    const identifiants = saisie(formulaireConnexion);
    if (fenetreConnexion.dataset.inscription)
    {
        await appelerApi('POST', 'inscription', identifiants);
        afficherMessage('Compte créé : vous êtes connecté.');
    }
    await appelerApi('POST', 'connexion', identifiants);
    fenetreConnexion.close();
    await chargerCompte();
    document.dispatchEvent(new Event('connexion'));
}

boutonCompte.onclick = async () =>
{
    if (!compte)
    {
        return afficherConnexion();
    }
    await fetch('api/deconnexion');
    location.href = 'index.html';
};

formulaireConnexion.onsubmit = (evenement) =>
{
    evenement.preventDefault();
    connecter();
};

document.querySelectorAll('.fermer').forEach((bouton) => bouton.onclick = () => bouton.closest('dialog').close());

if (boutonBascule)
{
    boutonBascule.onclick = () => afficherConnexion(!fenetreConnexion.dataset.inscription);
}

// Le numéro de version est affiché en pied de page
appelerApi('GET', 'version').then((application) => document.querySelector('#version').textContent = application.version);

// Toute erreur non traitée est affichée à l'utilisateur
window.addEventListener('unhandledrejection', (evenement) => afficherMessage(evenement.reason.message));
