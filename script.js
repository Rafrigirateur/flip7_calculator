document.addEventListener('DOMContentLoaded', () => {
    const handContainer = document.querySelector('.hand');
    const bonusBanner = document.querySelector('.bonus-gold');

    // Gestion du clic sur le Deck (Ajout)
    document.querySelectorAll('.deck a').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const url = link.getAttribute('href');
            
            // On envoie la requête au PHP en arrière-plan
            await fetch(url);
            
            // On rafraîchit uniquement la zone de la "main"
            updateHand();
        });
    });

    // Fonction pour mettre à jour la main et le bonus sans recharger la page
    async function updateHand() {
        const response = await fetch('index.php');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // On remplace le contenu de la main
        const newHand = doc.querySelector('.hand').innerHTML;
        handContainer.innerHTML = newHand;

        // On gère l'affichage dynamique du bandeau Bonus Flip7
        const newBonus = doc.querySelector('.bonus-gold');
        if (newBonus) {
            if (!document.querySelector('.bonus-gold')) {
                const banner = document.createElement('div');
                banner.className = 'bonus-gold';
                banner.innerText = 'BONUS FLIP7 (+15) ACTIVÉ !';
                handContainer.after(banner);
            }
        } else {
            const currentBonus = document.querySelector('.bonus-gold');
            if (currentBonus) currentBonus.remove();
        }

        // Ré-attacher les événements sur les nouvelles cartes de la main (pour suppression)
        attachHandEvents();
    }

    function attachHandEvents() {
        document.querySelectorAll('.hand a').forEach(link => {
            link.onclick = async (e) => {
                e.preventDefault();
                await fetch(link.getAttribute('href'));
                updateHand();
            };
        });
    }

    attachHandEvents();
});