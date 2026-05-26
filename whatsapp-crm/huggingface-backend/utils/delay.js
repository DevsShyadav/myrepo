/**
 * Delay utility for campaign pacing
 */

/**
 * Sleep for specified milliseconds
 * @param {number} ms - Milliseconds to sleep
 * @returns {Promise<void>}
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Generate random delay between min and max seconds
 * @param {number} minSeconds - Minimum delay in seconds
 * @param {number} maxSeconds - Maximum delay in seconds
 * @returns {number} Random delay in milliseconds
 */
function getRandomDelay(minSeconds = 120, maxSeconds = 300) {
    const min = minSeconds * 1000;
    const max = maxSeconds * 1000;
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Wait for random delay between messages
 * @param {number} minSeconds - Minimum delay in seconds
 * @param {number} maxSeconds - Maximum delay in seconds
 * @returns {Promise<number>} Actual delay used in ms
 */
async function randomDelay(minSeconds = 120, maxSeconds = 300) {
    const delay = getRandomDelay(minSeconds, maxSeconds);
    await sleep(delay);
    return delay;
}

module.exports = {
    sleep,
    getRandomDelay,
    randomDelay
};
