/**
 * Phone number formatting utility
 * Handles Indian phone numbers for WhatsApp
 */

/**
 * Clean and format phone number for WhatsApp
 * @param {string} phone - Raw phone number
 * @returns {string} Formatted phone number (e.g., 919876543210)
 */
function formatForWhatsApp(phone) {
    if (!phone) return '';

    // Remove all non-digit characters
    let cleaned = phone.toString().replace(/[^\d]/g, '');

    // Handle Indian numbers
    if (cleaned.length === 10) {
        // Add country code
        cleaned = '91' + cleaned;
    } else if (cleaned.startsWith('0') && cleaned.length === 11) {
        // Remove leading 0 and add country code
        cleaned = '91' + cleaned.substring(1);
    } else if (cleaned.startsWith('91') && cleaned.length === 12) {
        // Already has country code
    } else if (cleaned.startsWith('+91')) {
        cleaned = cleaned.substring(1);
    }

    return cleaned;
}

/**
 * Get WhatsApp chat ID from phone number
 * @param {string} phone - Phone number
 * @returns {string} WhatsApp chat ID (e.g., 919876543210@c.us)
 */
function toChatId(phone) {
    const formatted = formatForWhatsApp(phone);
    return `${formatted}@c.us`;
}

/**
 * Extract phone number from WhatsApp chat ID
 * @param {string} chatId - WhatsApp chat ID
 * @returns {string} Phone number without @c.us
 */
function fromChatId(chatId) {
    if (!chatId) return '';
    return chatId.replace('@c.us', '').replace('@s.whatsapp.net', '');
}

/**
 * Validate if phone number looks valid
 * @param {string} phone - Phone number to validate
 * @returns {boolean} Is valid format
 */
function isValidFormat(phone) {
    const cleaned = formatForWhatsApp(phone);
    // Indian numbers: 91 + 10 digits
    return /^91[6-9]\d{9}$/.test(cleaned);
}

module.exports = {
    formatForWhatsApp,
    toChatId,
    fromChatId,
    isValidFormat
};
