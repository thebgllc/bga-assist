function expectSubset(actual, subset) {
  for (const [key, value] of Object.entries(subset)) {
    expect(actual[key]).toEqual(value);
  }
}

module.exports = {
  expectSubset
};
