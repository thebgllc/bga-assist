function allSameOrAllDifferent(values) {
  const unique = new Set(values);
  return unique.size === 1 || unique.size === 3;
}

function isValidSet(cards) {
  if (!Array.isArray(cards) || cards.length !== 3) {
    return false;
  }

  const colors = cards.map((c) => c.color);
  const numbers = cards.map((c) => c.number);
  const shadings = cards.map((c) => c.shading);

  return allSameOrAllDifferent(colors) && allSameOrAllDifferent(numbers) && allSameOrAllDifferent(shadings);
}

function calculateScore(setCount, streak = 0) {
  const base = Number(setCount) || 0;
  return streak >= 3 ? base * 2 : base;
}

function getLegalMoves(cards) {
  if (!Array.isArray(cards)) {
    return [];
  }

  const moves = [];
  for (let i = 0; i < cards.length; i += 1) {
    for (let j = i + 1; j < cards.length; j += 1) {
      for (let k = j + 1; k < cards.length; k += 1) {
        const combo = [cards[i], cards[j], cards[k]];
        if (isValidSet(combo)) {
          moves.push(combo);
        }
      }
    }
  }

  return moves;
}

module.exports = {
  isValidSet,
  calculateScore,
  getLegalMoves
};
