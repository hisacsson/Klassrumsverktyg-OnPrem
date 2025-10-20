// /assets/js/timer-confetti.js

// Definiera Konfetti-system i global scope så det är tillgängligt för anrop
window.TimerConfetti = {
    canvas: null,
    ctx: null,
    particles: [],
    animationId: null,
    colors: ['#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#ffeb3b', '#ff9800'],
    
    // Initiera canvas och bakgrundsdata
    init: function() {
        // Skapa canvas-element om det inte redan finns
        if (!this.canvas) {
            this.canvas = document.createElement('canvas');
            this.canvas.id = 'timer-confetti-canvas';
            this.canvas.style.position = 'fixed';
            this.canvas.style.top = '0';
            this.canvas.style.left = '0';
            this.canvas.style.width = '100%';
            this.canvas.style.height = '100%';
            this.canvas.style.pointerEvents = 'none'; // Viktigt! Detta gör att klick går genom canvas
            this.canvas.style.zIndex = '9998'; // Strax under modalen
            document.body.appendChild(this.canvas);
        }

        // Sätt canvas-storlek till fönsterstorlek
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;

        // Hämta canvas context
        this.ctx = this.canvas.getContext('2d');
        
        return this;
    },
    
    // Skapa partiklar som exploderar från centrum
    createParticles: function() {
        this.particles = [];
        const numberOfParticles = 400; // ÄNNU fler partiklar!
        const spawnHeight = this.canvas.height * 0.4; // Låt dem starta från övre 40%

        for (let i = 0; i < numberOfParticles; i++) {
            // Slumpmässig startposition
            const startX = Math.random() * this.canvas.width;
            const startY = Math.random() * spawnHeight;

            // Ännu bredare och mer slumpmässig spridning
            const angle = Math.PI / 2 + (Math.random() - 0.5) * 1.5;

            // Snabbare initial hastighet
            const speed = 7 + Math.random() * 9;

            const size = 7 + Math.random() * 12; // Lite större
            const rotationSpeed = (Math.random() - 0.5) * 0.3; // Lite snabbare rotation
            const type = Math.floor(Math.random() * 3);
            const gravity = 0.15 + Math.random() * 0.07; // Variera gravitationen
            const drag = 0.95 + Math.random() * 0.04; // Variera luftmotståndet
            const lifetime = 150 + Math.random() * 100; // Ännu längre livstid

            this.particles.push({
                x: startX,
                y: startY,
                size: size,
                color: this.colors[Math.floor(Math.random() * this.colors.length)],
                speed: speed,
                velocity: {
                    x: Math.cos(angle) * speed,
                    y: Math.sin(angle) * speed
                },
                angle: angle,
                rotation: 0,
                rotationSpeed: rotationSpeed,
                type: type,
                opacity: 1,
                gravity: gravity,
                drag: drag,
                lifetime: lifetime
            });
        }

        // Valfritt: Lägg till en central "burst" av konfetti direkt
        const centerX = this.canvas.width / 2;
        const centerY = this.canvas.height * 0.75; // Lite högre upp än mitten för att sprida sig mer
        const centralBurstCount = 50;
        for (let i = 0; i < centralBurstCount; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = 3 + Math.random() * 5;
            const size = 5 + Math.random() * 9;
            const lifetimeOffset = Math.random() * 30; // Lite variation i livslängd
            this.particles.push({
                x: centerX,
                y: centerY,
                size: size,
                color: this.colors[Math.floor(Math.random() * this.colors.length)],
                speed: speed,
                velocity: {
                    x: Math.cos(angle) * speed,
                    y: Math.sin(angle) * speed
                },
                angle: angle,
                rotation: (Math.random() - 0.5) * 0.2,
                rotationSpeed: (Math.random() - 0.5) * 0.1,
                type: Math.floor(Math.random() * 3),
                opacity: 1,
                gravity: 0.08 + Math.random() * 0.03,
                drag: 0.96,
                lifetime: 70 + lifetimeOffset
            });
        }

        return this;
    },
    
    // Skapa en enskild explosion
    createExplosion: function(x, y, count) {
        for (let i = 0; i < count; i++) {
            // Slumpmässig riktning i radianer (0-2π)
            const angle = Math.random() * Math.PI * 2;
            
            // Slumpmässig hastighet för "utskjutning"
            const speed = 2 + Math.random() * 6;
            
            // Olika storlekar av konfetti
            const size = 5 + Math.random() * 10;
            
            // Slumpmässig rotationshastighet
            const rotationSpeed = (Math.random() - 0.5) * 0.2;
            
            // Slumpmässig konfettityp (0: rektangel, 1: cirkel, 2: triangel)
            const type = Math.floor(Math.random() * 3);
            
            // Förbered för en långsam nedtrappning av hastigheten
            const gravity = 0.12;
            const drag = 0.95;
            
            // Förskjutning inom explosionspunkten för mer naturligt utseende
            const offsetX = (Math.random() - 0.5) * 30;
            const offsetY = (Math.random() - 0.5) * 30;
            
            this.particles.push({
                x: x + offsetX,
                y: y + offsetY,
                size: size,
                color: this.colors[Math.floor(Math.random() * this.colors.length)],
                speed: speed,
                velocity: {
                    x: Math.cos(angle) * speed,
                    y: Math.sin(angle) * speed
                },
                angle: angle,
                rotation: 0,
                rotationSpeed: rotationSpeed,
                type: type,
                opacity: 1,
                gravity: gravity,
                drag: drag,
                // Lägg till en liten slumpmässig fördröjning innan konfettin börjar blekna bort
                lifetime: 80 + Math.random() * 60
            });
        }
    },
    
    // Animera partiklar
    animate: function() {
        if (!this.ctx || !this.canvas) return;

        // Rensa canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Uppdatera och rita partiklar
        for (let i = 0; i < this.particles.length; i++) {
            const p = this.particles[i];
            
            // Uppdatera position baserat på hastighet
            p.x += p.velocity.x;
            p.y += p.velocity.y;
            
            // Tillämpa gravitation
            p.velocity.y += p.gravity;
            
            // Tillämpa luftmotstånd
            p.velocity.x *= p.drag;
            p.velocity.y *= p.drag;
            
            // Uppdatera rotation
            p.rotation += p.rotationSpeed;
            
            // Minska livstid
            p.lifetime -= 1;
            
            // Minska opacitet baserat på livstid
            if (p.lifetime <= 20) {
                p.opacity = p.lifetime / 20;
            }
            
            // Rita partikeln
            this.ctx.save();
            this.ctx.beginPath();
            this.ctx.translate(p.x, p.y);
            this.ctx.rotate(p.rotation);
            this.ctx.fillStyle = p.color;
            this.ctx.globalAlpha = Math.max(0, p.opacity);
            
            // Rita olika former baserat på typ
            switch(p.type) {
                case 0: // Rektangel
                    this.ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                    break;
                    
                case 1: // Cirkel
                    this.ctx.beginPath();
                    this.ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                    this.ctx.fill();
                    break;
                    
                case 2: // Triangel
                    this.ctx.beginPath();
                    this.ctx.moveTo(0, -p.size / 2);
                    this.ctx.lineTo(p.size / 2, p.size / 2);
                    this.ctx.lineTo(-p.size / 2, p.size / 2);
                    this.ctx.closePath();
                    this.ctx.fill();
                    break;
            }
            
            this.ctx.restore();
        }

        // Ta bort partiklar som har blivit transparenta
        this.particles = this.particles.filter(p => p.opacity > 0);

        // Fortsätt animation om det finns partiklar kvar
        if (this.particles.length > 0) {
            const self = this;
            this.animationId = requestAnimationFrame(function() {
                self.animate();
            });
        } else {
            this.cleanup();
        }
        
        return this;
    },
    
    // Starta konfetti
    start: function() {
        this.init();
        this.createParticles();
        this.animate();
        return this;
    },
    
    // Stoppa konfetti
    stop: function() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        this.cleanup();
        return this;
    },
    
    // Städa upp canvas
    cleanup: function() {
        // Ta bort canvas-elementet
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        this.canvas = null;
        this.ctx = null;
        return this;
    }
};