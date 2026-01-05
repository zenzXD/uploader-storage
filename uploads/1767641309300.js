/* ╔════════════════════════════════════════════════════════════════════╗
   ║  📄 MAIN CORE SYSTEM - WORKING BUTTON FIX                         ║
   ║  ⚡ Created by Adam Hasani (Flora System)                          ║
   ║  🛠️ Version: 3 Days History, 20k RAM, WORKING BUTTON             ║
   ╚════════════════════════════════════════════════════════════════════╝
*/

import fs from "fs";
import path, { dirname } from "path";
import { fileURLToPath } from 'url';
import readline from "readline";
import pino from "pino";
import chalk from "chalk";
import moment from "moment-timezone";
import { 
    makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason, 
    fetchLatestBaileysVersion, 
    delay, 
    getContentType, 
    downloadContentFromMessage,
    downloadMediaMessage, 
    generateWAMessageFromContent, 
    prepareWAMessageMedia 
} from "@whiskeysockets/baileys";

// 📂 LOCAL IMPORTS
import { handlePluginError } from './toolkit/errorHandler.js'; 
import deadlineChecker from './scheduler/deadline_reminder.js';
import stg from "./toolkit/setting.js";
import makeInMemoryStore from "./toolkit/store.js"; 
import emtData from "./toolkit/transmitter.js";
import { checkAntiLink } from './plugins/group/antilink.js'; 

// ⚙️ GLOBAL PATH SETUP
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
global.__dirname = __dirname;

// ==========================================================================
// 🎨 THEME & COLORS (PALETTE)
// ==========================================================================
const c = {
    gold: chalk.bold.hex('#FFD700'),
    cyan: chalk.hex('#00FFFF'),
    green: chalk.hex('#32CD32'),
    gray: chalk.hex('#808080'),
    neon: chalk.bold.hex('#39FF14'),
    red: chalk.bold.hex('#FF0000'),
    pink: chalk.hex('#FF69B4')
};

// ==========================================================================
// 🚀 INITIALIZATION & DATABASE
// ==========================================================================
const { reset, timer, labvn, saveLidCache, messageContent, checkSpam } = emtData;
const { isPrefix } = stg;
const logger = pino({ level: "silent" });

// Global Variables
global.plugins = {};
global.categories = {};
global.lidCache = {};
global.aiChatHistory = new Map();
global.lastBotMessageId = null;
global.messageSourceMap = new Map();
global.buttonUsageCache = new Map();
global.deadlineSchedulerStarted = false;
global.manualMessageCache = new Map(); 
global.conn = null;
global.selfconn = null;

// Database Check
if (!fs.existsSync('./database')) fs.mkdirSync('./database');
const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
global.q = (text) => new Promise((resolve) => rl.question(text, resolve));
global.rl = rl;

try { global.initDB(); } catch(e) {}

// ==========================================================================
// 📱 BOT CONFIGURATIONS
// ==========================================================================
const botConfigs = [
    {
        name: 'Public Bot',
        number: '84326025517',
        selfbotMode: false,
        sessionPath: './session' 
    },
    {
        name: 'Private Bot',
        number: '6287751121269',
        selfbotMode: true, 
        sessionPath: './session_self' 
    }
];

const getOwnerJid = () => {
    const num = Array.isArray(global.ownerNumber) ? global.ownerNumber[0] : global.ownerNumber;
    return num ? num.replace(/\D/g, "") + "@s.whatsapp.net" : "";
};

// ==========================================================================
// 🚑 ERROR HANDLING
// ==========================================================================
const handleFatalError = (err) => {
    const errStr = String(err);
    if (errStr.includes("Decipheriv") || errStr.includes("authenticate data") || errStr.includes("badSession")) {
        console.log(c.gold(`⚠️  Warning: Decryption glitch detected (Ignored).`));
        return; 
    }
    console.error(c.red("⚠️ Uncaught Exception:"), err);
};

process.on('uncaughtException', handleFatalError);
process.on('unhandledRejection', handleFatalError);

// ==========================================================================
// 🧹 SYSTEM MAINTENANCE
// ==========================================================================
function systemMaintenance() {
    const directories = ['.npm', '.cache', 'tmp', 'temp', 'node_modules/.cache'];
    directories.forEach(folder => {
        const folderPath = path.join(__dirname, folder);
        if (fs.existsSync(folderPath)) {
            try { 
                if (['tmp', 'temp'].includes(folder)) {
                    fs.rmSync(folderPath, { recursive: true, force: true });
                    fs.mkdirSync(folderPath);
                } else {
                    fs.rmSync(folderPath, { recursive: true, force: true });
                }
            } catch (e) {}
        }
    });
}
systemMaintenance(); 

// ==========================================================================
// 🔄 SCHEDULED TASKS (CRON JOBS)
// ==========================================================================
setInterval(async () => {
    try {
        const now = Date.now();
        const db = getDB();
        
        const maxCacheAge = 24 * 60 * 60 * 1000;
        const maxCacheSize = 20000;

        if (global.manualMessageCache.size > 0) {
            const keys = Array.from(global.manualMessageCache.keys());
            let deletedCount = 0;
            
            for (let i = 0; i < 200; i++) {
                if (keys.length <= i) break;
                const key = keys[i];
                const msg = global.manualMessageCache.get(key);
                
                if (msg) {
                    const msgTime = getMsgTimestamp(msg) * 1000;
                    if ((now - msgTime) > maxCacheAge) {
                        global.manualMessageCache.delete(key);
                        deletedCount++;
                    } else if (global.manualMessageCache.size > maxCacheSize) {
                        global.manualMessageCache.delete(key);
                        deletedCount++;
                    }
                }
            }
        }

        for (const u of Object.values(db.Private || {})) {
            if (u.isPremium?.isPrem && (u.isPremium.time = Math.max(u.isPremium.time - 6e4, 0)) === 0) 
                u.isPremium.isPrem = false;
        }

        for (const g of Object.values(db.Grup || {})) {
            const gf = g.gbFilter || {};
            for (const [type, mode] of Object.entries({ close: "announcement", open: "not_announcement" })) {
                const t = gf[type];
                if (t?.active && now >= t.until) {
                    try {
                        const targetConn = global.conn || global.selfconn;
                        await targetConn.groupSettingUpdate(g.Id, mode);
                        delete gf[type];
                    } catch (err) {}
                }
            }
        }
        saveDB();

        global.lastBackup = global.lastBackup || 0;
        if (global.autoBackup && (now - global.lastBackup >= 144e5)) { 
            try {
                const { default: backup } = await import(`./plugins/owner/backup.js?update=${Date.now()}`);
                const owners = (Array.isArray(global.ownerNumber) ? global.ownerNumber : [global.ownerNumber]).map(n => n.replace(/\D/g, "") + "@s.whatsapp.net");
                const targetConn = global.conn || global.selfconn;
                await backup.run(targetConn, {}, { chatInfo: { chatId: owners[0] } });
                global.lastBackup = now;
            } catch (err) {}
        }
    } catch (e) {}
}, 60000); 

// ==========================================================================
// 🧠 HELPER FUNCTIONS
// ==========================================================================
const xp = async (conn, msg, chatId, senderId, isGroup) => {
    try {
        const groupData = getGc(chatId);
        if (groupData?.mute) {
            const meta = await conn.groupMetadata(chatId);
            if (!meta.participants.some(p => p.admin && p.id === senderId)) return true; 
        }
        if (!global.public && !global.ownerNumber.includes((senderId || "").replace(/\D/g, ""))) return true;
    } catch(e) {}
    return false;
}

function getMsgTimestamp(msg) {
    const ts = msg.messageTimestamp;
    if (typeof ts === 'number') return ts;
    if (typeof ts === 'object' && ts !== null) return ts.low || ts.seconds || 0;
    return 0;
}

function levenshteinDistance(str1, str2) {
    const track = Array(str2.length + 1).fill(null).map(() => Array(str1.length + 1).fill(null));
    for (let i = 0; i <= str1.length; i += 1) track[0][i] = i;
    for (let j = 1; j <= str2.length; j += 1) {
        for (let i = 1; i <= str1.length; i += 1) {
            const indicator = str1[i - 1] === str2[j - 1] ? 0 : 1;
            track[j][i] = Math.min(track[j][i - 1] + 1, track[j - 1][i] + 1, track[j - 1][i - 1] + indicator);
        }
    }
    return track[str2.length][str1.length];
}

function findClosestCommand(inputCommand, threshold = 2) {
    const allCommands = [];
    Object.values(global.plugins).forEach(p => {
        if (typeof p.command === 'string') allCommands.push(p.command);
        else if (Array.isArray(p.command)) allCommands.push(...p.command);
    });
    let closest = null, minDesc = Infinity;
    for (const cmd of allCommands) {
        const dist = levenshteinDistance(inputCommand, cmd);
        if (dist < minDesc && dist <= threshold) { minDesc = dist; closest = cmd; }
    }
    return closest;
}

// ==========================================================================
// 💻 MAIN BOT LOGIC
// ==========================================================================
const startBot = async (config) => {
    if (!fs.existsSync(config.sessionPath)) fs.mkdirSync(config.sessionPath, { recursive: true });

    const { state, saveCreds } = await useMultiFileAuthState(config.sessionPath);
    const { version } = await fetchLatestBaileysVersion();
    
    const storePath = `./database/store_${config.name.replace(/\s+/g, '_').toLowerCase()}.json`;
    const store = makeInMemoryStore();
    if (fs.existsSync(storePath)) {
        try { if (store.readFromFile) store.readFromFile(storePath); } catch (err) {}
    }
    setInterval(() => { try { if (store.writeToFile) store.writeToFile(storePath); } catch (err) {} }, 5_000);

    const conn = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        syncFullHistory: false, 
        markOnlineOnConnect: false,
        messageCache: 3750,
        logger,
        browser: ["Ubuntu", "Chrome", "20.0.04"],
        connectTimeoutMs: 60000, 
        defaultQueryTimeoutMs: 0,
        keepAliveIntervalMs: 30000, 
        retryRequestDelayMs: 5000,
        getMessage: async (key) => {
            if (store) {
                const msg = await store.loadMessage(key.remoteJid, key.id);
                return msg?.message || undefined;
            }
            return undefined;
        }
    });

    if (config.selfbotMode) {
        conn.internalReadMessages = conn.readMessages;
        conn.internalSendPresence = conn.sendPresenceUpdate;
        conn.readMessages = async (keys) => {
            const isStatus = keys.some(k => k.remoteJid === 'status@broadcast');
            if (isStatus) { try { return await conn.internalReadMessages(keys); } catch(e) {} }
            return null; 
        };
        conn.sendPresenceUpdate = async (type, to) => { return null; };
    }

    // ==========================================================================
    // 🔘 WORKING BUTTON SYSTEM - NATIVE FORMAT
    // ==========================================================================
    
    conn.sendButton = async (jid, text, buttons, quoted, options = {}) => {
        try {
            const buttonMessage = {
                text: text,
                footer: options.footer || global.botName || 'Flora Bot',
                buttons: buttons.map(btn => ({
                    buttonId: btn.id,
                    buttonText: { displayText: btn.display },
                    type: 1
                })),
                headerType: 1
            };

            if (options.image) {
                buttonMessage.image = { url: options.image };
                buttonMessage.headerType = 4;
            }

            if (options.video) {
                buttonMessage.video = { url: options.video };
                buttonMessage.headerType = 5;
            }

            await conn.sendMessage(jid, buttonMessage, { quoted });
            console.log(c.green(`  ✅ Button sent successfully`));
            
        } catch (error) {
            console.error(c.red(`  ❌ Button Error: ${error.message}`));
            
            let fallbackText = `${text}\n\n${options.footer || ''}\n\n📱 *Pilih Opsi:*\n`;
            buttons.forEach((btn, i) => {
                fallbackText += `${i + 1}. ${btn.display}\n   Reply: ${btn.id}\n`;
            });

            if (options.image) {
                await conn.sendMessage(jid, { image: { url: options.image }, caption: fallbackText }, { quoted });
            } else {
                await conn.sendMessage(jid, { text: fallbackText }, { quoted });
            }
        }
    };

    conn.sendList = async (jid, title, text, buttonText, sections, quoted, options = {}) => {
        try {
            const listMessage = {
                text: text,
                footer: options.footer || global.botName || 'Flora Bot',
                title: title,
                buttonText: buttonText,
                sections: sections
            };

            await conn.sendMessage(jid, listMessage, { quoted });
            console.log(c.green(`  ✅ List sent successfully`));
            
        } catch (error) {
            console.error(c.red(`  ❌ List Error: ${error.message}`));
            
            let fallbackText = `${title}\n\n${text}\n\n${options.footer || ''}\n\n`;
            sections.forEach((section, i) => {
                fallbackText += `\n📂 *${section.title}*\n`;
                section.rows.forEach((row, j) => {
                    fallbackText += `${j + 1}. ${row.title}\n`;
                    if (row.description) fallbackText += `   ${row.description}\n`;
                });
            });

            await conn.sendMessage(jid, { text: fallbackText }, { quoted });
        }
    };

    conn.sendButtonImage = async (jid, buffer, contentText, footerText, buttons, quoted) => {
        try {
            const buttonMessage = {
                image: buffer,
                caption: contentText,
                footer: footerText,
                buttons: buttons.map(btn => ({
                    buttonId: btn.id,
                    buttonText: { displayText: btn.display },
                    type: 1
                })),
                headerType: 4
            };

            await conn.sendMessage(jid, buttonMessage, { quoted });
            
        } catch (error) {
            console.error(c.red(`  ❌ Button Image Error: ${error.message}`));
            
            let fallbackText = `${contentText}\n\n${footerText}\n\n`;
            buttons.forEach((btn, i) => {
                fallbackText += `${i + 1}. ${btn.display}\n`;
            });

            await conn.sendMessage(jid, { image: buffer, caption: fallbackText }, { quoted });
        }
    };

    if (!config.selfbotMode) global.conn = conn;        
    else global.selfconn = conn;  
    
    conn.originalSendMessage = conn.sendMessage;
    conn.ev.on("creds.update", saveCreds);
    store.bind(conn.ev);

    if (!state.creds?.me?.id) {
        console.log(c.cyan(`\n┌─────────────────────────────────────────────┐`));
        console.log(c.cyan(`│ 🌐  ${config.name.toUpperCase().padEnd(39)} │`));
        console.log(c.cyan(`└─────────────────────────────────────────────┘`));
        console.log(c.green(`  📱 Number: ${config.number}`));
        console.log(c.cyan(`  🔐 Requesting pairing code...`));
        await delay(5000);
        try {
            const code = await conn.requestPairingCode(config.number.replace(/\D/g, ""));
            console.log(`\n  ${chalk.bgHex('#39FF14').black(' 🔑 PAIRING CODE ')}  ${c.gold(code?.match(/.{1,4}/g)?.join(' • '))}\n`);
            
            if (config.selfbotMode && global.conn) {
                const ownerJid = getOwnerJid();
                if (ownerJid) await global.conn.sendMessage(ownerJid, { text: `🔐 *KODE PAIRING SELFBOT*\n\nCode: *${code}*` });
            }
        } catch (e) { console.error("Pairing Code Request Failed"); }
    }

    conn.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect } = update;
        if (connection === 'open') {
            console.log(c.neon(`  ✓ [${config.name}] Online & Ready!`));
            console.log(c.green(`  🔘 Button System: LOADED (Native Format)`));
            if (config.selfbotMode) console.log(c.green(`  🛡️  Silent Ghost Mode: ON`));
            if (!global.deadlineSchedulerStarted && !config.selfbotMode) {
                deadlineChecker(conn);
                global.deadlineSchedulerStarted = true;
            }
        } else if (connection === 'close') {
            const reason = lastDisconnect.error?.output?.statusCode;
            if (reason !== DisconnectReason.loggedOut) {
                console.log(c.cyan(`  🔄 [${config.name}] Re-establishing uplink...`));
                setTimeout(() => startBot(config), 5000);
            }
        }
    });

    async function trackAndSendMessage(conn, chatId, pluginData, messageOptions, quotedMsg, originalSendMessage) {
        const sentMessage = await originalSendMessage(chatId, messageOptions, { quoted: quotedMsg });
        if (sentMessage?.key?.id) {
            global.messageSourceMap.set(sentMessage.key.id, { ...pluginData });
            setTimeout(() => global.messageSourceMap.delete(sentMessage.key.id), 300000);
        }
        return sentMessage;
    }

    // ==================================================================
    // 📩 MESSAGE PROCESSING HANDLER
    // ==================================================================
    conn.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages?.[0];
        if (!msg?.message) return;

        if (msg.key && msg.key.id) {
            global.manualMessageCache.set(msg.key.id, msg);
        }

        // ==================================================================
        // 👁️ ANTI VIEW ONCE (STEALTH MODE)
        // ==================================================================
        if (config.selfbotMode && !msg.key.fromMe) {
            try {
                const m = msg.message;
                const viewOnce = m?.viewOnceMessageV2Extension?.message || m?.viewOnceMessageV2?.message || m?.viewOnceMessage?.message;
                if (viewOnce) {
                    const mediaTypes = ['image', 'video', 'audio'];
                    const mediaType = mediaTypes.find(t => viewOnce[`${t}Message`]);
                    const mediaMsg = viewOnce[`${mediaType}Message`];

                    if (mediaType && mediaMsg) {
                        const buffer = await downloadMediaMessage(
                            { message: { [`${mediaType}Message`]: mediaMsg } },
                            'buffer', {}, 
                            { logger, reuploadRequest: conn.updateMediaMessage }
                        );

                        if (buffer) {
                            const senderName = msg.pushName || "Unknown";
                            const caption = `🔓 *OPENED VIEWONCE* (Stealth)\n👤 ${senderName}\n📝 ${mediaMsg.caption || ''}`;
                            const selfJid = conn.user.id.includes(':') ? conn.user.id.split(':')[0] + '@s.whatsapp.net' : conn.user.id;

                            let opts = { caption };
                            if (mediaType === 'image') opts.image = buffer;
                            else if (mediaType === 'video') opts.video = buffer;
                            else if (mediaType === 'audio') { opts.audio = buffer; opts.mimetype = 'audio/mpeg'; opts.ptt = false; }
                            
                            await conn.sendMessage(selfJid, opts);
                            console.log(c.pink(`  🔓 ViewOnce Saved (Stealth Mode).`));
                        }
                    }
                }
            } catch (e) { console.log(c.red(`  ❌ Anti-ViewOnce Error: ${e.message}`)); }
        }

        // ==================================================================
        // 🗑️ ULTIMATE ANTI-DELETE (3 DAYS + LIVE GROUP FIX)
        // ==================================================================
        if (config.selfbotMode) {
            try {
                const msgType = getContentType(msg.message);
                if (msgType === "protocolMessage" && msg.message.protocolMessage.type === 0) {
                    const key = msg.message.protocolMessage.key;
                    
                    let deletedMsg = null;
                    deletedMsg = global.manualMessageCache.get(key.id);

                    if (!deletedMsg) {
                        try { deletedMsg = await store.loadMessage(key.remoteJid, key.id); } catch(e) {}
                    }
                    
                    if (!deletedMsg) {
                        await delay(1500);
                        try { deletedMsg = await store.loadMessage(key.remoteJid, key.id); } catch(e) {}
                    }
                    
                    if (!deletedMsg) {
                        await delay(1500);
                        try { deletedMsg = await store.loadMessage(key.remoteJid, key.id); } catch(e) {}
                    }
                    
                    if (deletedMsg && deletedMsg.message) {
                        const selfJid = conn.user.id.includes(':') ? conn.user.id.split(':')[0] + '@s.whatsapp.net' : conn.user.id;
                        
                        let realSender = deletedMsg.key.participant || deletedMsg.participant || key.participant || key.remoteJid;
                        if (realSender.includes(':')) realSender = realSender.split(':')[0] + '@s.whatsapp.net';
                        
                        const senderNum = realSender.split('@')[0];
                        const senderName = deletedMsg.pushName || "Unknown";
                        
                        let sourceInfo = "📩 Private Chat";
                        
                        if (key.remoteJid === 'status@broadcast') {
                            sourceInfo = "📸 Status WhatsApp (SW)";
                        } else if (key.remoteJid.endsWith('@g.us')) {
                            let groupName = "Group Chat";
                            try {
                                const gMeta = await conn.groupMetadata(key.remoteJid);
                                groupName = gMeta.subject;
                            } catch (e) {
                                groupName = store.chats.get(key.remoteJid)?.subject || "Unknown Group";
                            }
                            sourceInfo = `👥 ${groupName}`;
                        }

                        const timeNow = moment().tz("Asia/Jakarta").format("HH:mm:ss");
                        const dateNow = moment().tz("Asia/Jakarta").format("DD/MM/YYYY");

                        let caption = `⚡ *ANTI-DELETE MONITOR (3 DAYS)* ⚡\n`;
                        caption += `──────────────────────────\n`;
                        caption += `👤 *Author* : ${senderName}\n`;
                        caption += `📱 *Contact* : ${senderNum}\n`; 
                        caption += `📍 *Source* : ${sourceInfo}\n`;
                        caption += `🕒 *Time* : ${timeNow} WIB (${dateNow})\n`;
                        caption += `──────────────────────────\n`;
                        caption += `🗑️ *DELETED CONTENT:*\n\n`;

                        let msgContent = deletedMsg.message;
                        if (msgContent.viewOnceMessageV2) msgContent = msgContent.viewOnceMessageV2.message;
                        else if (msgContent.viewOnceMessage) msgContent = msgContent.viewOnceMessage.message;

                        const type = getContentType(msgContent);
                        
                        if (type === "conversation" || type === "extendedTextMessage") {
                            const text = msgContent.conversation || msgContent.extendedTextMessage.text;
                            await conn.sendMessage(selfJid, { text: caption + text });
                        } else {
                            const isMedia = ['imageMessage', 'videoMessage', 'audioMessage', 'stickerMessage', 'documentMessage'].includes(type);
                            if (isMedia) {
                                const stream = await downloadContentFromMessage(msgContent[type], type.replace('Message', ''));
                                let buffer = Buffer.from([]);
                                for await (const chunk of stream) buffer = Buffer.concat([buffer, chunk]);
                                
                                let mediaOpts = { caption: caption + (msgContent[type].caption || "") };
                                if (type === 'imageMessage') mediaOpts.image = buffer;
                                else if (type === 'videoMessage') mediaOpts.video = buffer;
                                else if (type === 'stickerMessage') { delete mediaOpts.caption; mediaOpts.sticker = buffer; }
                                else if (type === 'audioMessage') { delete mediaOpts.caption; mediaOpts.audio = buffer; mediaOpts.mimetype = 'audio/mpeg'; }
                                else if (type === 'documentMessage') { 
                                    mediaOpts.document = buffer; 
                                    mediaOpts.mimetype = msgContent[type].mimetype;
                                    mediaOpts.fileName = msgContent[type].fileName;
                                }
                                await conn.sendMessage(selfJid, mediaOpts);
                            }
                        }
                    } else {
                        console.log(c.gray(`  [Anti-Delete] Msg ${key.id} not found.`));
                    }
                }
            } catch (err) { console.error("Anti-Delete Error", err); }
        }

        if (msg.key.remoteJid === 'status@broadcast' && !msg.key.fromMe) {
            if (config.selfbotMode) {
                setTimeout(async () => {
                    try { 
                        await conn.internalReadMessages([msg.key]); 
                    } catch (e) {}
                }, Math.floor(Math.random() * 3000) + 1000);
            }
            return;
        }

        if (config.selfbotMode && msg.key.fromMe) {
            try {
                const msgType = getContentType(msg.message);
                const content = msg.message[msgType];
                if (content?.contextInfo?.stanzaId) {
                    await conn.internalReadMessages([{ 
                        remoteJid: msg.key.remoteJid, 
                        id: content.contextInfo.stanzaId, 
                        participant: content.contextInfo.participant || msg.key.remoteJid 
                    }]);
                }
            } catch(e) {}
        }

        const msgTime = getMsgTimestamp(msg);
        const timeNow = Math.floor(Date.now() / 1000);
        if (timeNow - msgTime > 30) return;

        if (config.selfbotMode) { if (!msg.key.fromMe) return; } 
        else { if (msg.key.fromMe) return; }

        try {
            // ==================================================================
            // 🔘 BUTTON RESPONSE PARSER - WORKING VERSION
            // ==================================================================
            const buttonResponseMsg = msg.message?.buttonsResponseMessage;
            const listResponseMsg = msg.message?.listResponseMessage;
            const templateButtonReply = msg.message?.templateButtonReplyMessage;

            if (buttonResponseMsg) {
                msg.body = buttonResponseMsg.selectedButtonId;
                msg.message.conversation = buttonResponseMsg.selectedButtonId;
                console.log(c.cyan(`  🔘 Button Clicked: ${msg.body}`));
            }

            if (listResponseMsg) {
                msg.body = listResponseMsg.singleSelectReply?.selectedRowId;
                msg.message.conversation = listResponseMsg.singleSelectReply?.selectedRowId;
                console.log(c.cyan(`  📋 List Selected: ${msg.body}`));
            }

            if (templateButtonReply) {
                msg.body = templateButtonReply.selectedId;
                msg.message.conversation = templateButtonReply.selectedId;
                console.log(c.cyan(`  🔘 Template Button: ${msg.body}`));
            }

            const interactiveResponse = msg.message?.interactiveResponseMessage;
            if (interactiveResponse) {
                const nativeFlow = interactiveResponse.nativeFlowResponseMessage;
                if (nativeFlow) {
                    try {
                        const params = JSON.parse(nativeFlow.paramsJson);
                        msg.body = params.id;
                        msg.message.conversation = params.id;
                        console.log(c.cyan(`  ⚡ Interactive Click: ${msg.body}`));
                    } catch (e) {
                        console.error(c.red('  ❌ Parse interactive error'));
                    }
                }
            }

            const { chatId, isGroup, senderId, pushName, senderIsAdmin, senderIsOwner } = exCht(msg, conn);
            let { textMessage } = messageContent(msg); 
            
            let groupMeta = null; 
            if (isGroup) { 
                groupMeta = await getMetadata(chatId, conn); 
                if (groupMeta) await saveLidCache(groupMeta); 
            }
            replaceLid(msg);

            const timestamp = c.gray(`[${moment().format('HH:mm:ss')}]`);
            const context = isGroup ? (groupMeta?.subject || 'Group') : 'Private';
            if (textMessage) console.log(`${timestamp} ${isGroup ? '👥' : '💬'} ${config.selfbotMode ? c.green(context) : c.cyan(context)} • ${c.neon(pushName)} ${c.gray('→')} ${chalk.white(textMessage)}`);

            if (config.selfbotMode) {
                let isSelfActive = true; 
                try { 
                    if (fs.existsSync('./database/selfbot_status.json')) 
                        isSelfActive = JSON.parse(fs.readFileSync('./database/selfbot_status.json')).active; 
                } catch (e) {}
                if (!isSelfActive) {
                    const cleanText = (textMessage || '').toLowerCase().trim();
                    if (!['.self on', `${isPrefix}self on`].includes(cleanText)) return; 
                }
            }

            if (isGroup) { 
                try { 
                    if (await checkAntiLink(conn, msg, { chatId, senderId, senderIsAdmin, senderIsOwner })) return; 
                } catch (e) {} 
            }
            
            const docMsg = msg.message?.documentMessage;
            if (docMsg) {
                const mime = docMsg.mimetype || '';
                if (['application/pdf', 'application/msword', 'application/vnd.ms-excel'].some(t => mime.includes(t))) {
                    const tourlPlugin = Object.values(global.plugins).find(p => p.command && p.command.includes('tourl'));
                    if (tourlPlugin) tourlPlugin.run(conn, msg, { chatInfo: { chatId, isGroup, senderId, pushName }, store, isPrefix: true, command: 'tourl' }).catch(() => {});
                }
            }

            let parsedPrefix = parseMessage(msg, isPrefix);
            
            const botId = conn.user.id.split(':')[0];
            if (textMessage && textMessage.startsWith(`@${botId}`)) {
                let rawContent = textMessage.replace(new RegExp(`@${botId}`, 'g'), '').trim();
                if (rawContent && !['halo','hi','p','bot'].includes(rawContent.split(/\s+/)[0].toLowerCase())) {
                    let words = rawContent.split(/\s+/);
                    let found = findClosestCommand(words[0].toLowerCase(), 2);
                    if (found) {
                        const newBody = `${isPrefix || '.'}${found} ${rawContent.replace(new RegExp(`^${words[0]}`, 'i'), '').trim()}`;
                        msg.body = newBody; 
                        parsedPrefix = parseMessage({ ...msg, body: newBody }, isPrefix);
                    }
                }
            }

            if (msg.message && !msg.key.fromMe) {
                const chatInfoForBefore = { chatId, isGroup, senderId, pushName };
                for (const plugin of Object.values(global.plugins)) {
                    if (plugin.before && typeof plugin.before === 'function') {
                        try { 
                            await plugin.before(conn, msg, { chatInfo: chatInfoForBefore, store }); 
                        } catch (err) { 
                            console.error('Error on plugin before:', err); 
                        }
                    }
                }
            }
            
            let executed = false;
            const userDb = getUser(senderId);
            const isPrem = config.selfbotMode || userDb?.value?.isPremium?.isPrem || false;
            const mode = global.setting?.botSetting?.Mode || "private";
            const allowedMode = config.selfbotMode || isPrem || !((mode === "group" && !isGroup) || (mode === "private" && isGroup));

            if (allowedMode && !banned(senderId) && parsedPrefix) {
                const { commandText, chatInfo } = parsedPrefix;
                for (const [fileName, plugin] of Object.entries(global.plugins)) {
                    if (!plugin?.command?.includes(commandText)) continue;
                    
                    const prefixUsed = !!parsedPrefix.prefix;
                    const allowRun = plugin.prefix === "both" || (plugin.prefix === false && !prefixUsed) || (plugin.prefix !== "both" && plugin.prefix !== false && prefixUsed);
                    if (!allowRun) continue;

                    authUser(msg, chatInfo);
                    if (await checkSpam(chatInfo.senderId, conn, chatInfo.chatId)) return;
                    
                    const effectiveIsOwner = config.selfbotMode || await global.isOwner(plugin, conn, msg);
                    if ((plugin.premium && !isPrem) || (plugin.owner && !effectiveIsOwner)) continue;

                    try {
                        await conn.sendMessage(chatInfo.chatId, { react: { text: '⏳', key: msg.key } });
                        conn.sendMessage = (chatId, opts, other) => trackAndSendMessage(conn, chatId, { fileName, command: commandText, tags: plugin.tags, desc: plugin.desc }, opts, msg, conn.originalSendMessage);
                        
                        await plugin.run(conn, msg, { ...parsedPrefix, command: commandText, isPrefix, store });
                        
                        await conn.sendMessage(chatInfo.chatId, { react: { text: '✅', key: msg.key } });
                        conn.sendMessage = conn.originalSendMessage;
                        executed = true;
                        if (userDb) { 
                            userDb.data.cmd = (userDb.data.cmd || 0) + 1; 
                            saveDB(getDB()); 
                        }
                    } catch (err) {
                        await conn.sendMessage(chatInfo.chatId, { react: { text: '❌', key: msg.key } });
                        await handlePluginError(conn, chatInfo.chatId, err, { fileName, command: commandText });
                    }
                    break;
                }
            }

            if (executed) return;
            if (banned(senderId)) return;
            if (config.selfbotMode) return;

            await Promise.all([ () => labvn(textMessage, msg, conn, chatId) ].map(t => t()));
            for (const f of [groupFilter, badwordFilter, xp]) { 
                if (await f(conn, msg, chatId, senderId, isGroup)) return; 
            }
            
            if (msg.message.reactionMessage) await rctKey(msg, conn);
            
            await Promise.all([ 
                () => shopHandle(conn, msg, textMessage, chatId, senderId), 
                () => handleGame(conn, msg, chatId, textMessage) 
            ].map(t => t()));
            
            if (await global.chtEmt(textMessage, msg, senderId, chatId, conn)) return;
            
            if (!executed && textMessage) {
                const chatInfo = { chatId, isGroup, senderId, pushName };
                for (const plugin of Object.values(global.plugins)) {
                    if (plugin.isReplyHandler) {
                        try { 
                            await plugin.run(conn, msg, { chatInfo, prefix: isPrefix || '.', command: '', args: [] }); 
                        } catch (err) {}
                    }
                }
            }

        } catch (err) { 
            console.error(c.red(`  ✕ [${config.name}] Error:`), err); 
        }
    });

    conn.ev.on('group-participants.update', async (update) => {
       try {
            const { id: chatId, participants, action } = update;
            const w = enWelcome(chatId) && action === 'add';
            const l = enLeft(chatId) && ['remove', 'leave'].includes(action);
            if ((!w && !l) || !Array.isArray(participants)) return;
            const { text, media } = w ? getWelTxt(chatId) : getLeftTxt(chatId);
            for (const p of participants) {
                const jid = typeof p === 'string' ? p : (p?.id || '');
                if (!jid) continue;
                const t = `@${jid.split('@')[0]}`;
                const finalText = text.replace(/@user|%user/g, t).replace(/%time/g, moment().format('HH:mm'));
                const opts = { text: finalText, mentions: [jid] };
                
                if (media) {
                    const pth = `./temp/${media}`;
                    if (fs.existsSync(pth)) {
                        const ext = path.extname(media).toLowerCase();
                        opts[['.mp4', '.gif'].includes(ext) ? 'video' : 'image'] = { url: pth };
                        opts.caption = finalText;
                        delete opts.text;
                    }
                }
                await conn.sendMessage(chatId, opts);
            }
        } catch (e) {}
    });
};

console.log(c.green(`
╔════════════════════════════════════════════════════════╗
║      ⚡  FLORA INTELLIGENCE SYSTEM v3.0  ⚡           ║
║      [Ultimate Anti-Delete + Working Button]           ║
╚════════════════════════════════════════════════════════╝`));

const initEstate = async () => {
  try {
    const report = await loadPlug();
    console.log(c.gray(`\n ┌───  `) + c.neon(`SYSTEM DIAGNOSTICS`));
    console.log(c.gray(` │  `) + c.gold(`✦ ${report.ok} Modules Loaded`));
    console.log(c.gray(` │  `) + c.green(`✦ Button System: Native Format (Working)`));
    console.log(c.gray(` └─────────────────────────────────────────────\n`));
    for (const cfg of botConfigs) { 
        await startBot(cfg); 
        await delay(5000); 
    }
  } catch (e) {}
};

initEstate();

fs.watchFile(__filename, () => {
    console.log(c.gold.italic(`⟳  Core Updated. Rebooting...`));
    process.exit();
});