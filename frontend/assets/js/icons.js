(function () {
    const svgNs = 'http://www.w3.org/2000/svg';
    const symbols = {
        dashboard: '<rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect>',
        wallet: '<path d="M4 7h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h13"></path><path d="M16 11h4v4h-4a2 2 0 0 1 0-4z"></path><path d="M6 5l10-2v4"></path>',
        list: '<rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="M9 9h6"></path><path d="M9 13h6"></path><path d="M9 17h3"></path>',
        tag: '<path d="M20 10l-8.5 8.5a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L12 2h8v8z"></path><circle cx="16" cy="6" r="1.5"></circle>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        user: '<circle cx="12" cy="8" r="4"></circle><path d="M20 21a8 8 0 0 0-16 0"></path>',
        expense: '<path d="M12 3v14"></path><path d="M7 12l5 5 5-5"></path><path d="M5 21h14"></path>',
        pie: '<path d="M21 12a9 9 0 1 1-9-9v9z"></path><path d="M13 3.1A9 9 0 0 1 20.9 11H13z"></path>',
        trend: '<path d="M3 17l6-6 4 4 7-7"></path><path d="M14 8h6v6"></path>',
        add: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        edit: '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>',
        delete: '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
        check: '<path d="M20 6L9 17l-5-5"></path>',
        x: '<path d="M18 6L6 18"></path><path d="M6 6l12 12"></path>',
        globe: '<circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 0 20"></path><path d="M12 2a15.3 15.3 0 0 0 0 20"></path>',
        alert: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        search: '<circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.3-4.3"></path>',
        pin: '<path d="M12 17v5"></path><path d="M8 3h8l1 6 3 3H4l3-3 1-6z"></path>',
        shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>'
    };

    let sprite = document.querySelector('svg.icon-sprite');
    if (!sprite) {
        sprite = document.createElementNS(svgNs, 'svg');
        sprite.setAttribute('class', 'icon-sprite');
        sprite.setAttribute('aria-hidden', 'true');
        sprite.setAttribute('focusable', 'false');
        document.body.insertBefore(sprite, document.body.firstChild);
    }

    Object.keys(symbols).forEach((name) => {
        const id = 'icon-' + name;
        if (document.getElementById(id)) return;

        const symbol = document.createElementNS(svgNs, 'symbol');
        symbol.setAttribute('id', id);
        symbol.setAttribute('viewBox', '0 0 24 24');
        symbol.innerHTML = symbols[name];
        sprite.appendChild(symbol);
    });
}());

