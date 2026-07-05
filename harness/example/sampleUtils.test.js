const { isValidSet, calculateScore, getLegalMoves } = require('../../src/setgame.utils.js');

describe('isValidSet', () => {
  test('all same color is valid', () => {
    const cards = [
      { color: 'red', number: 1, shading: 'solid' },
      { color: 'red', number: 2, shading: 'striped' },
      { color: 'red', number: 3, shading: 'open' }
    ];
    expect(isValidSet(cards)).toBe(true);
  });

  test('all different colors is valid', () => {
    const cards = [
      { color: 'red', number: 1, shading: 'solid' },
      { color: 'green', number: 2, shading: 'striped' },
      { color: 'blue', number: 3, shading: 'open' }
    ];
    expect(isValidSet(cards)).toBe(true);
  });

  test('two same one different is invalid', () => {
    const cards = [
      { color: 'red', number: 1, shading: 'solid' },
      { color: 'red', number: 2, shading: 'striped' },
      { color: 'blue', number: 3, shading: 'open' }
    ];
    expect(isValidSet(cards)).toBe(false);
  });
});

describe('calculateScore', () => {
  test('base score for standard set', () => {
    expect(calculateScore(1, 0)).toBe(1);
  });

  test('bonus multiplier for 3-in-a-row', () => {
    expect(calculateScore(2, 3)).toBe(4);
  });
});

describe('getLegalMoves', () => {
  test('finds at least one legal set', () => {
    const cards = [
      { color: 'red', number: 1, shading: 'solid' },
      { color: 'green', number: 2, shading: 'striped' },
      { color: 'blue', number: 3, shading: 'open' },
      { color: 'red', number: 1, shading: 'solid' }
    ];

    expect(getLegalMoves(cards).length).toBeGreaterThan(0);
  });
});
