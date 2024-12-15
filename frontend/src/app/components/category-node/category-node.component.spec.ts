import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CategoryNodeComponent } from './category-node.component';

describe('CategoryNodeComponent', () => {
  let component: CategoryNodeComponent;
  let fixture: ComponentFixture<CategoryNodeComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CategoryNodeComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CategoryNodeComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
