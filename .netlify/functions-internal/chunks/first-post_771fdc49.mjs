async function getMod() {
						return import('./prerender_e6f6f40b.mjs').then(n => n.b);
					}
					const collectedLinks = "@@ASTRO-LINKS@@";
					const collectedStyles = "@@ASTRO-STYLES@@";
					const collectedScripts = "@@ASTRO-SCRIPTS@@";
					const defaultMod = { __astroPropagation: true, getMod, collectedLinks, collectedStyles, collectedScripts };

export { defaultMod as default };
