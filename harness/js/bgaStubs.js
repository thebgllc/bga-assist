global.dojo = {
  place: jest.fn(),
  query: jest.fn(() => ({ forEach: jest.fn(), length: 0 })),
  style: jest.fn(),
  addClass: jest.fn(),
  removeClass: jest.fn(),
  connect: jest.fn(),
  on: jest.fn(),
  animateProperty: jest.fn(() => ({ play: jest.fn() })),
  require: jest.fn()
};

global.gameui = {
  player_id: 1,
  gamedatas: {},
  addTooltip: jest.fn(),
  ajaxcall: jest.fn(),
  showMessage: jest.fn()
};

const notificationHandlers = {};

global.bgaNotification = {
  register: (type, handler) => {
    notificationHandlers[type] = handler;
  },
  trigger: (type, args) => {
    if (notificationHandlers[type]) {
      notificationHandlers[type]({ args });
    }
  }
};
